<?php

namespace ArneGroskurth\TempFile;


class TempFile {

    /**
     * @var string
     */
    protected $filePath;

    /**
     * @var resource
     */
    protected $fileHandle;

    /**
     * @var bool
     */
    protected $doUnlinkOnDestruction = true;


    /**
     * @throws TempFileException
     */
    public function __construct() {

        $this->filePath = $this->generateTempFilePath();

        $this->openFileHandle();
    }


    public function __destruct() {

        $this->closeFileHandle();

        if($this->doUnlinkOnDestruction) {

            @unlink($this->filePath);
        }
    }


    /**
     * @return int
     */
    public function ftell() {

        return ftell($this->fileHandle);
    }


    /**
     * @param int $offset
     * @param int $whence
     *
     * @return int
     */
    public function fseek($offset, $whence = SEEK_SET) {

        return fseek($this->fileHandle, $offset, $whence);
    }


    /**
     * @param int $length
     *
     * @return string
     * @throws TempFileException
     */
    public function fread($length) {

        if(empty($length)) {

            throw new TempFileException('Length argument must be greater then zero.');
        }

        return fread($this->fileHandle, $length);
    }


    /**
     * @param string $string
     * @param int $length
     *
     * @return int
     * @throws TempFileException
     */
    public function fwrite($string, $length = null) {

        if($length === 0 || empty($string)) {

            return 0;
        }

        $return = ($length === null) ? fwrite($this->fileHandle, $string) : fwrite($this->fileHandle, $string, $length);

        if($return === false) {

            throw new TempFileException('Error writing to temp file.');
        }

        return $return;
    }


    /**
     * @return bool
     */
    public function eof() {

        return feof($this->fileHandle);
    }


    /**
     * {@inheritdoc}
     */
    public function getSize() {

        $pos = $this->ftell();

        $this->fseek(0, SEEK_END);
        $return = $this->ftell();
        $this->fseek($pos);

        return $return;
    }


    /**
     * Returns file content as string.
     *
     * @return string
     * @throws TempFileException
     */
    public function getContent() {

        $size = $this->getSize();

        if($size > 0) {

            $pos = $this->ftell();

            $this->fseek(0);
            $return = $this->fread($size);
            $this->fseek($pos);

            return $return;
        }

        return '';
    }


    /**
     * Tries to detect MIME-Type using PHP's Fileinfo extension.
     *
     * @return string
     */
    public function detectMime() {

        $fileInfo = new \finfo(FILEINFO_MIME);

        return $fileInfo->buffer($this->getContent(), FILEINFO_MIME) ?: null;
    }


    /**
     * Moves temporary file to a persistent file under the given path.
     * Subsequent calls to fwrite() on this object will be applied to the persisted file.
     * Path parameter defaults to some path inside sys_get_temp_dir() which is generally wiped on system start.
     *
     * @param string $path
     * @param int $chmod
     *
     * @return \SplFileObject
     * @throws TempFileException
     */
    public function persist($path = null, $chmod = null) {

        if($path === null) {

            $path = $this->generateTempFilePath();
        }

        $this->closeFileHandle();

        if(is_file($path) && !unlink($path)) {

            throw new TempFileException(sprintf('Destination file already exists and could not be deleted: %s.', $path));
        }

        if(!rename($this->filePath, $path)) {

            throw new TempFileException(sprintf('Could not persist temporary file to %s.', $path));
        }

        $this->filePath = $path;
        $this->doUnlinkOnDestruction = false;

        $this->openFileHandle('r');

        if($chmod !== null && !chmod($path, $chmod)) {

            throw new TempFileException('Could not chmod() persisted file.');
        }

        return new \SplFileObject($path, 'r+');
    }


    /**
     * Writes temporary file contents to a persistent file.
     * Path parameter defaults to some path inside sys_get_temp_dir() which is generally wiped on system start.
     *
     * @param string $path
     * @param int $chmod
     *
     * @return \SplFileObject
     * @throws TempFileException
     */
    public function persistCopy($path = null, $chmod = null) {

        if($path === null) {

            $path = $this->generateTempFilePath();
        }

        fflush($this->fileHandle);

        if(!copy($this->filePath, $path)) {

            throw new TempFileException(sprintf('Could not persist temporary file to %s.', $path));
        }

        if($chmod !== null && !chmod($path, $chmod)) {

            throw new TempFileException('Could not chmod() persisted file.');
        }

        return new \SplFileObject($path, 'r+');
    }


    /**
     * Builds, sends and afterwards returns a Response object.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws TempFileException
     */
    public function send() {

        return $this->buildResponse()->send();
    }


    /**
     * @param string $fileName
     * @param string $contentType
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws TempFileException
     */
    public function buildResponse($fileName = 'file.tmp', $contentType = null) {

        if(!class_exists('\Symfony\Component\HttpFoundation\Response')) {

            throw new TempFileException('Could not find class "\Symfony\Component\HttpFoundation\Response".');
        }

        try {

            $response = new \Symfony\Component\HttpFoundation\Response();
            $response->setContent($this->getContent());
            $response->setLastModified(new \DateTime());
            $response->headers->set('Content-Disposition', sprintf('attachment; filename=%s', rawurlencode($fileName)));
            $response->headers->set('Content-Length', $this->getSize());
            $response->headers->set('Content-Type', ($contentType === null) ? ($this->detectMime() ?: 'application/octet-stream') : $contentType);

            return $response;
        }
        catch(\Exception $e) {

            throw new TempFileException('Could not create response.', 0, $e);
        }
    }


    /**
     * Executes a given callback function
     *
     * @param callable $callback
     *
     * @return $this
     * @throws TempFileException
     */
    public function accessPath(callable $callback) {

        $this->closeFileHandle();

        call_user_func($callback, $this->filePath);

        $this->openFileHandle('r+');

        return $this;
    }


    /**
     * @param string $mode
     *
     * @throws TempFileException
     */
    protected function openFileHandle($mode = 'w+') {

        if($this->fileHandle !== null) {

            throw new TempFileException();
        }

        if(!($fileHandle = fopen($this->filePath, $mode))) {

            throw new TempFileException('Could not open file handle to temporary file.');
        }

        $this->fileHandle = $fileHandle;
    }


    protected function closeFileHandle() {

        if($this->fileHandle !== null) {

            fflush($this->fileHandle);

            if(!fclose($this->fileHandle)) {

                throw new TempFileException();
            }

            $this->fileHandle = null;
        }
    }


    /**
     * @return string
     */
    protected function generateTempFilePath() {

        return tempnam(sys_get_temp_dir(), 'TempFile-');
    }


    /**
     * Returns a TempFile object representing a copy of an existing file.
     * The file pointer is set to the beginning to the file.
     *
     * @param string $path
     *
     * @return TempFile
     * @throws TempFileException
     */
    public static function fromFile($path) {

        if(!is_file($path) || !is_readable($path)) {

            throw new TempFileException(sprintf('File %s does not exist or is not readable.', $path));
        }

        $tempFile = new static();
        $tempFile->closeFileHandle();

        if(!copy($path, $tempFile->filePath)) {

            throw new TempFileException(sprintf('Could not read in file %s.', $path));
        }

        $tempFile->openFileHandle('r+');

        return $tempFile;
    }
}
