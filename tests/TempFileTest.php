<?php

namespace ArneGroskurth\TempFile\Tests;

use ArneGroskurth\TempFile\TempFile;


class TempFileTest extends \PHPUnit_Framework_TestCase {

    public function testWriteRead() {

        $testContent = $this->getTestContent();

        $tempFile = new TempFile();
        $tempFile->fwrite($testContent);

        static::assertEquals($testContent, $tempFile->getContent());
    }


    public function testSize() {

        $testContent = $this->getTestContent();

        $tempFile = new TempFile();
        $tempFile->fwrite($testContent);

        static::assertEquals(strlen($testContent), $tempFile->getSize());
    }


    public function testMimeDetection() {

        $tempFile = new TempFile();
        $tempFile->fwrite('Some ASCII data');

        static::assertEquals('text/plain; charset=us-ascii', $tempFile->detectMime());
    }


    public function testResponseBuilding() {

        $testContent = $this->getTestContent();

        $tempFile = new TempFile();
        $tempFile->fwrite($testContent);

        $response = $tempFile->buildResponse();

        static::assertEquals(md5($testContent), md5($response->getContent()));
    }


    public function testSending() {

        $testContent = $this->getTestContent();

        $tempFile = new TempFile();
        $tempFile->fwrite($testContent);

        ob_start();

        $tempFile->send();

        $rawResponse = ob_get_clean();

        static::assertEquals(md5($testContent), md5($rawResponse));
    }


    public function testPersist() {

        $testContent = $this->getTestContent();

        $tempFile = new TempFile();
        $tempFile->fwrite($testContent);

        $tempFile->accessPath(function($path) use (&$tempFilePath) {

            $tempFilePath = $path;
        });

        $file = $tempFile->persist();

        // content got copied correctly
        static::assertEquals(md5($testContent), md5(file_get_contents($file->getPathname())));

        // old temporary file does no longer exist
        static::assertFalse(is_file($tempFilePath));

        unset($tempFile);

        // persisted file exists after temporary file has been destroyed
        static::assertTrue(is_file($file->getPathname()));


        // cleanup
        $path = $file->getPathname();
        unset($file);
        unlink($path);
    }


    public function testPersistCopy() {

        $testContent = $this->getTestContent();

        $tempFile = new TempFile();
        $tempFile->fwrite($testContent);

        $file = $tempFile->persistCopy();

        // content got copied correctly
        static::assertEquals(md5($testContent), md5(file_get_contents($file->getPathname())));

        unset($tempFile);

        // persisted file exists after temporary file has been destroyed
        static::assertTrue(is_file($file->getPathname()));


        // cleanup
        $path = $file->getPathname();
        unset($file);
        unlink($path);
    }


    public function testFromFile() {

        $testContent = $this->getTestContent();

        $fileHandle = tmpfile();
        fwrite($fileHandle, $testContent);

        $filePath = stream_get_meta_data($fileHandle)['uri'];

        $tempFile = TempFile::fromFile($filePath);

        static::assertEquals(md5($testContent), md5($tempFile->getContent()));
    }


    public function testRemoval() {

        $tempFile = new TempFile();
        $tempFile->accessPath(function($path) use (&$tempFilePath) {

            $tempFilePath = $path;
        });

        static::assertTrue(is_file($tempFilePath));

        unset($tempFile);

        static::assertFalse(is_file($tempFilePath));
    }


    protected function getTestContent($length = 16384) {

        if(function_exists('openssl_random_pseudo_bytes ')) {

            return openssl_random_pseudo_bytes($length);
        }

        for($return = ''; strlen($return) < $length;) {

            $return .= md5(mt_rand());
        }

        return substr($return, 0, $length);
    }
}