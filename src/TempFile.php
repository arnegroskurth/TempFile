<?php

namespace ArneGroskurth\TempFile;


class TempFile extends \SplTempFileObject {

    /**
     * Returns file content as string.
     *
     * @return string
     */
    public function getContent() {

        $pos = $this->ftell();

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

        $file = new \SplFileObject($path);

        for($pos = $this->ftell(), $this->fseek(0); !$this->eof();) {

            $file->fwrite($this->fread($chunkSize));
        }
        $this->fseek($pos);

        if($mode !== null && !chmod($path, $mode)) {

            throw new TempFileException('Could not chmod() persisted file.');
        }

        return $file;
    }


    /**
     * Builds, sends and afterwards returns a Response object.
     *
     * @return \Symfony\Component\HttpFoundation\Response
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

        $response = new \Symfony\Component\HttpFoundation\Response();
        $response->setContent($this->fread($this->getSize()));
        $response->setLastModified(new \DateTime());
        $response->headers->set('Content-Disposition', sprintf('attachment; filename=%s', rawurlencode($fileName)));
        $response->headers->set('Content-Length', $this->getSize());
        $response->headers->set('Content-Type', $this->detectMime() ?: 'application/octet-stream');

        return $response;
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