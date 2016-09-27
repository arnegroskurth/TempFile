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
     * @throws TempFileException
     */
    public function __construct() {

        $this->filePath = tempnam(sys_get_temp_dir(), 'TempFile-');

        $this->openFileHandle();
    }


    public function __destruct() {

        @fclose($this->fileHandle);
        @unlink($this->filePath);
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
     * Writes temporary file contents to a persistent file.
     * Path parameter defaults to some path inside sys_get_temp_dir() which is generally wiped on system start.
     *
     * @param string $path
     * @param int $mode
     * @param int $chunkSize
     *
     * @return \SplFileObject
     * @throws TempFileException
     */
    public function persist($path = null, $mode = 0600, $chunkSize = 4096) {

        if($path === null) {

            $path = tempnam(sys_get_temp_dir(), 'TempFile-');
        }

        $file = new \SplFileObject($path, 'w+');

        $pos = $this->ftell();

        for($this->fseek(0); !$this->eof();) {

            $file->fwrite($this->fread($chunkSize));
        }

        $this->fseek($pos);
        $file->fseek(0);

        if($mode !== null && !chmod($path, $mode)) {

            throw new TempFileException('Could not chmod() persisted file.');
        }

        return $file;
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

        if(!fflush($this->fileHandle)) {

            throw new TempFileException('Could not flush temporary file contents to disk.');
        }

        if(!fclose($this->fileHandle)) {

            throw new TempFileException();
        }

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

        if(!($fileHandle = fopen($this->filePath, $mode))) {

            throw new TempFileException('Could not open file handle to temporary file.');
        }

        $this->fileHandle = $fileHandle;
    }


    /**
     * Returns a TempFile object representing a copy of an existing file.
     *
     * @param string $path
     * @param int $chunkSize
     *
     * @return TempFile
     * @throws TempFileException
     */
    public static function fromFile($path, $chunkSize = 4096) {

        if(!is_file($path) || !is_readable($path)) {

            throw new TempFileException(sprintf('Could not read in file "%s".', $path));
        }

        $file = new \SplFileObject($path, 'r');

        $tempFile = new static();

        while(!$file->eof()) {

            $tempFile->fwrite($file->fread($chunkSize));
        }

        return $tempFile;
    }
}