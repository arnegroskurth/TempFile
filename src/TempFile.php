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
        $this->fileHandle = fopen($this->filePath, 'w+');

        if(!$this->fileHandle) {

            throw new TempFileException('Could not create temporary file.');
        }
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
     */
    public function fread($length) {

        return fread($this->fileHandle, $length);
    }


    /**
     * @param string $string
     * @param int $length
     *
     * @return int
     */
    public function fwrite($string, $length = null) {

        if($length === null) {

            return fwrite($this->fileHandle, $string);
        }

        else {

            return fwrite($this->fileHandle, $string, $length);
        }
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
     */
    public function getContent() {

        $pos = $this->ftell();

        $this->fseek(0);
        $return = $this->fread($this->getSize());
        $this->fseek($pos);

        return $return;
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

        for($pos = $this->ftell(), $this->fseek(0); !$this->eof();) {

            $file->fwrite($this->fread($chunkSize));
        }

        $this->fseek($pos);
        $file->seek(0);

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
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws TempFileException
     */
    public function buildResponse($fileName = 'file.tmp') {

        if(!class_exists('\Symfony\Component\HttpFoundation\Response')) {

            throw new TempFileException('Could not find class "\Symfony\Component\HttpFoundation\Response".');
        }

        try {

            $response = new \Symfony\Component\HttpFoundation\Response();
            $response->setContent($this->getContent());
            $response->setLastModified(new \DateTime());
            $response->headers->set('Content-Disposition', sprintf('attachment; filename=%s', rawurlencode($fileName)));
            $response->headers->set('Content-Length', $this->getSize());
            $response->headers->set('Content-Type', $this->detectMime() ?: 'application/octet-stream');

            return $response;
        }
        catch(\Exception $e) {

            throw new TempFileException('Could not create response.', 0, $e);
        }
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