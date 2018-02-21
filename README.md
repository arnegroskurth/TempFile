# TempFile

[![Build Status](https://travis-ci.org/arnegroskurth/temp-file.svg?branch=master)](https://travis-ci.org/arnegroskurth/temp-file)
[![codecov](https://codecov.io/gh/arnegroskurth/temp-file/branch/master/graph/badge.svg)](https://codecov.io/gh/arnegroskurth/temp-file)
[![License](https://poser.pugx.org/agroskurth/temp-file/license)](https://packagist.org/packages/agroskurth/temp-file)

TempFile is a small library inspired by the [SplTempFileObject](http://php.net/manual/en/class.spltempfileobject.php) providing solutions for commonly occurring tasks when dealing with temporary files.

## Setup

```bash
$ composer require arne-groskurth/temp-file
```

## Usage

```php
<?php

use ArneGroskurth\TempFile\TempFile;

$tempFile = new TempFile();

// TempFile offers all commonly used file-related functions including fread, fwrite, ftell, fseek and feof.
$tempFile->fwrite('Hello World!');

// Construct response object and write to stdout
// (Requires installation of package "symfony/http-foundation")
$tempFile->send();

// Obtain path-based access to temporary file within callback function
$tempFile->accessPath(function($path) {
    
    $content = file_get_contents($path);
    
    $content = str_replace('Hello World!', 'Got you!', $content);
    
    file_put_contents($path, $content);
});

// Echos 'Got yout!'
print $tempFile->getContent();

// Persist temporary file to some path
$tempFile->persist('/my/path/filename.ext');
```
