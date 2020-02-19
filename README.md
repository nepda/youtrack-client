YouTrack Client PHP Library
===========================

**Attention!**
This library is based on the now-**deprecated** REST API for YouTrack. jetBrains has release a new version of their REST API.
May be I'll create a new version of this library for the new API.

[![Build Status](https://travis-ci.org/nepda/youtrack-client.png?branch=master)](https://travis-ci.org/nepda/youtrack-client)
[![Packagist](https://img.shields.io/packagist/v/nepda/youtrack-client.svg)](https://packagist.org/packages/nepda/youtrack-client)

The bugtracker [YouTrack](http://www.jetbrains.com/youtrack/) provides a
[REST-API](https://www.jetbrains.com/help/youtrack/incloud/youtrack-rest-api-reference.html).
Because a lot of web applications are written in [PHP](http://php.net) I decided to write a client library for it.
To make it easier for developers to write connectors to YouTrack.

The initial development was sponsored by [Telematika GmbH](http://www.telematika.de).
The current development is made by nepda.

The source of this library is released under the BSD license (see LICENSE for details).

## Requirements

* PHP >= 5.4 (Tested with >= 5.6, Travis runs tests with ~~5.4, 5.5~~, 5.6, 7.0, 7.1, 7.2, 7.3 and 7.4)
* curl
* simplexml
* json
* YouTrack 3.0+ with REST-API enabled (currently, the production system runs with YouTrack 2019.1)

## Changelog

Please look into CHANGELOG for a list of the past releases.

## Usage

### With permanent token

Please look into
[the YouTrack documentation](https://www.jetbrains.com/help/youtrack/incloud/Log-in-to-YouTrack.html) on how
to create such a permanent token.

```php
<?php
require_once("YouTrack/Connection.php");
$youtrack = new \YouTrack\Connection("http://example.com", "perm:*****", null);
$issue = $youtrack->getIssue("TEST-1");
// ...
```

The `$password` parameter has to be `null` for permanent token login. This feature is dirty and will be fixed in version
2.*.

### With deprecated username/password login
```php
<?php
require_once("YouTrack/Connection.php");
$youtrack = new \YouTrack\Connection("http://example.com", "login", "password");
$issue = $youtrack->getIssue("TEST-1");
// ...
```

See `./examples` folder for more usage examples.

## Usage with ZF2 ZendSkeletonApplication

In your /init_autoloader.php

```php
<?php
// ... snip
if ($zf2Path) {
    if (isset($loader)) {
        $loader->add('Zend', $zf2Path);
    } else {
        include $zf2Path . '/Zend/Loader/AutoloaderFactory.php';
        Zend\Loader\AutoloaderFactory::factory(array(
            'Zend\Loader\StandardAutoloader' => array(
                'autoregister_zf' => true,
                'namespaces' => [                            // add this
                    'YouTrack' => 'vendor/YouTrack'          // ...
                ],                                           // ...
            )
        ));
    }
}
// ... snip
```

From now on you can use YouTrack-Client-PHP-Library from any file in you ZF2-App.

```php
<?php
// ...
// example
use YouTrack\Connection as YouTrackConnection;

class ExampleController extends AbstractActionController
{

    public function anyAction()
    {
        $youtrack = new YouTrackConnection("http://example.com", "login", "password");
        $issue = $youtrack->getIssue("TEST-1");
        // ...
    }
}
```

## Standalone setup with composer

Run the following commands to install composer and youtrack-client.

```sh
mkdir my-youtrack-project
cd my-youtrack-project

php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('SHA384', 'composer-setup.php') === '55d6ead61b29c7bdee5cccfb50076874187bd9f21f65d8991d46ec5cc90518f447387fb9f76ebae1fbbacf329e583e30') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
php composer-setup.php
php -r "unlink('composer-setup.php');"

php composer.phar require nepda/youtrack-client
```
(Please checkout the [latest composer setup on their page](https://getcomposer.org/download/))

Create a `./my-youtrack-project/client.php` file with content:

```php
<?php
define('YOUTRACK_URL', 'https://*your-url*.myjetbrains.com/youtrack');
define('YOUTRACK_USERNAME', '***');
define('YOUTRACK_PASSWORD', '***');
require_once 'vendor/autoload.php';
try {
    $youtrack = new YouTrack\Connection(
        YOUTRACK_URL,
        YOUTRACK_USERNAME . 'invalid',
        YOUTRACK_PASSWORD
    );
    echo 'Login correct.' . PHP_EOL;
    
    $issue = $youtrack->getIssue('TEST-123');
    // Now you can work with the issue or other $youtrack methods
} catch (\YouTrack\IncorrectLoginException $e) {
    echo 'Incorrect login or password.' . PHP_EOL;
}
```

    $issue = $youtrack->getIssue('TEST-123');
    // Now you can work with the issue or other $youtrack methods
} catch (\YouTrack\IncorrectLoginException $e) {
    echo 'Incorrect login or password.' . PHP_EOL;
}
```

With this simple setup you're ready to go.

## Tests

The testsuite depends on PHPUnit. You can install it with `composer.phar`:

```sh
curl -sS https://getcomposer.org/installer | php --
php composer.phar install
```

The unit tests are incomplete but you can run them using `phpunit` like this:

```sh
./vendor/bin/phpunit ./test
```

## Contributors

* [@jan0sch](https://github.com/jan0sch)
* [@jkswoods](https://github.com/jkswoods)
* [@Shyru](https://github.com/Shyru)
* [@nepda](https://github.com/nepda)
* [@richardhinkamp](https://github.com/richardhinkamp)
* [@Limelyte](https://github.com/Limelyte)
* [@1ed](https://github.com/1ed)
* [@openWebX](https://github.com/openWebX)
* [@wdamien](https://github.com/wdamien)
* [@angerslave](https://github.com/Angerslave)

(and more: https://github.com/nepda/youtrack-client/network/members)
