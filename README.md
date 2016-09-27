# TempFile

TempFile is a small library inspired by the [SplTempFileObject](http://php.net/manual/en/class.spltempfileobject.php) providing solutions for commonly occurring problems when dealing with temporary files.


## Installation

TempFile is available on [Packagist](https://packagist.org/packages/arne-groskurth/temp-file) and can therefore be installed via Composer:

```bash
$ composer require arne-groskurth/temp-file
```

TempFile suggests also installing the [symfony/http-foundation](https://github.com/symfony/http-foundation) to be able to directly convert temporary files to HTTP response objects.


## Example

```php
<?php

use ArneGroskurth\TempFile\TempFile;

$tempFile = new TempFile();

// TempFile offers all commonly used file-related functions including fread, fwrite, ftell, fseek and feof.
$tempFile->fwrite('Hello World!');

// Construct response object and write to stdout
// (Requires installation of package "symfony/http-foundation")
$tempFile->send();

// Persist temporary file to some path
$tempFile->persist('/my/path/filename.ext');
```


## License

MIT License

Copyright (c) 2016 Arne Groskurth

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
