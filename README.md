# ReCodEx REST API

[![Build Status](https://travis-ci.org/ReCodEx/api.svg?branch=master)](https://travis-ci.org/ReCodEx/api)
[![API documentation](https://img.shields.io/badge/docs-OpenAPI-orange.svg)](https://recodex.github.io/api/)
[![Test coverage](https://img.shields.io/coveralls/ReCodEx/api.svg)](https://coveralls.io/github/ReCodEx/api)

A REST API that provides access to the evaluation backend by clients.


## Installation

The web API requires a PHP runtime version at least 7. Which one depends on
actual configuration, there is a choice between _mod_php_ inside Apache,
_php-fpm_ with Apache or Nginx proxy or running it as standalone uWSGI script.
It is common that there are some PHP extensions, that have to be installed on
the system. Namely ZeroMQ binding (`php-zmq` package or similar), MySQL module
(`php-mysqlnd` package) and ldap extension module for CAS authentication
(`php-ldap` package). Make sure that the extensions are loaded in your `php.ini`
file (`/etc/php.ini` or files in `/etc/php.d/`).

The API depends on some other projects and libraries. For managing them
[Composer](https://getcomposer.org/) is used. It can be installed from system
repositories or downloaded from the website, where detailed instructions are as
well. Composer reads `composer.json` file in the project root and installs
dependencies to the `vendor/` subdirectory.

1. Clone the git repository
2. Run `composer install`
3. Create a database and fill in the access information in 
   `app/config/config.local.neon` (for an example, see 
   `app/config/config.local.neon.example`)
4. Setup the database schema by running `php www/index.php
   migrations:migrate`
5. Fill database with initial values by running `php www/index.php db:fill init`, after this database will contain:
	* Instance with administrator registered as local account with credentials username: `admin@admin.com`, password: `admin`
	* Runtime environments which ReCodEx can handle
	* Default single hardware group which might be used for workers
	* Pipelines for runtime environments which can be used when building exercises

## Web Server Setup

The simplest way to get started is to start the built-in PHP server in the root directory of your project:

	php -S localhost:4000 -t www

Then visit `http://localhost:4000` in your browser to see the welcome page.

For Apache or Nginx, setup a virtual host to point to the `www/` directory of the project and you should be ready to go.

It is **critical** that whole `app/`, `log/` and `temp/` directories are not accessible directly via a web browser. See [security warning](https://nette.org/security-warning). Also it is **highly recommended** to set up a HTTPS certificate for public access to the
API.

## Troubleshooting

In case of any issues first remove the Nette cache directory `temp/cache/` and
try again. This solves most of the errors. If it does not help, examine API logs
from `log/` directory of the API source or logs of your webserver.

## Running tests

The tests require `sqlite3` to be installed and accessible through $PATH.
Run them with the following command (feel free to adjust the path to php.ini):

```
php vendor/bin/tester -c /etc/php/php.ini tests
```

## Cron setup

The ReCodEx API requires some commands to be run periodically to allow sending 
notifications of assignment deadlines and cleaning up uploaded files. The 
recommended way of ensuring this is using a crontab like this:

```
04	00	*	*	*	php www/index.php notifications:assignment-deadlines "1 day"
02	00	*	*	*	php www/index.php db:cleanup:uploads
```

## Adminer

[Adminer](https://www.adminer.org/) is full-featured database management tool written in PHP and it is part of this Sandbox.
To use it, browse to the subdirectory `/adminer` in your project root (i.e. `http://localhost:4000/adminer`).


## License

- ReCodEx REST API: _MIT License_
- Nette: _New BSD License or GPL 2.0 or 3.0_
- Adminer: _Apache License 2.0 or GPL 2_
