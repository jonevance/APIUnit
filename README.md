# APIUnit

APIUnit is a framework to write repeatable tests of remote APIs.  It is based on the xUnit architecture and
is designed as a layer on top of [PHPUnit](https://phpunit.de).  

## Requirements

APIUnit requires [PHPUnit](https://phpunit.de) and [PHP cURL](http://php.net/manual/en/book.curl.php).

## Installation

First, [install PHPUnit](https://phpunit.de/getting-started.html) and [PHP cURL](http://php.net/manual/en/curl.installation.php), 
then place the APIUnit*.php files into a directory. This is sufficient to begin use, though adding the main script as 
an exectuable in your path could be helpful:

### Windows

Globally installing the executable involves the same procedure as 
manually [installing Composer on Windows](https://getcomposer.org/doc/00-intro.md#installation-windows):

1. Create a directory for PHP binaries; e.g., C:\bin

2. Append ;C:\bin to your PATH environment variable ([related help](http://stackoverflow.com/questions/6318156/adding-python-path-on-windows-7))

3. Place the APIUnit*.php files in C:\bin

4. Open a command line (press Windows+R, type cmd, ENTER)

5. Create a wrapping batch script (results in C:\bin\apiunit.cmd):

  ```batch
  C:\Users\username> cd C:\bin
  C:\bin> echo @php "%~dp0APIUnit.php" -c %* > apiunit.cmd
  C:\bin> exit
  ```

6. Open a new command line and confirm that you can execute APIUnit from any path:

  ```batch
  C:\Users\username> apiunit -v
  APIUnit 0.1 by Jon Vance
  ```
    
### Linux

  ```sh
  chmod +x APIUnit.php
  sudo mv APIUnit.php /usr/local/bin/apiunit
  apiunit -v
  ```
    

## Usage

Since an API can be written in virtually any language, APIUnit allows testing of individual API calls using the 
universal "language" of most modern APIs: [JSON](http://www.json.org).  Tests are defined using JSON and interpreted 
by APIUnit which outputs PHP test scripts ready for use by [PHPUnit](https://phpunit.de). 

Here is an example of a simple test:

```json
{
  "test":
  {
    "method": "get",
    "uri": "http://apiunit.popelli.com/helloworld.html",
    "output": "Hello, world"
  }
}
```

Save this as "simple.json" and execute `apiunit simple.json` - you should now have a directory called "tests" with a 
file called GetTest.php.  This file is a script that is ready to be used with [PHPUnit](https://phpunit.de) like this:
`phpunit tests/GetTest.php`.

For more examples and complete documentation, see our Wiki.

