ReCodEx REST API
=============

[![Build Status](https://travis-ci.org/ReCodEx/api.svg?branch=master)](https://travis-ci.org/ReCodEx/api)

A REST API that provides access to the evaluation backend by clients.


Installation
------------

1. Clone the git repository
2. Run `composer install`
3. Create a database and fill in the access information in 
   `app/config/config.local.neon` (for an example, see 
   `app/config/config.local.neon.example`)
4. Setup the database schema by running `php www/index.php 
   orm:schema-tool:update --force`
5. Fill database with initial values by running `php www/index.php 
   db:fill`


Web Server Setup
----------------

The simplest way to get started is to start the built-in PHP server in the root directory of your project:

	php -S localhost:4000 -t www

Then visit `http://localhost:4000` in your browser to see the welcome page.

For Apache or Nginx, setup a virtual host to point to the `www/` directory of the project and you
should be ready to go.

It is CRITICAL that whole `app/`, `log/` and `temp/` directories are not accessible directly
via a web browser. See [security warning](https://nette.org/security-warning).


Requirements
------------

PHP 7.0 or higher. To check whether server configuration meets the minimum requirements for
Nette Framework browse to the directory `/checker` in your project root (i.e. `http://localhost:4000/checker`).


Adminer
-------

[Adminer](https://www.adminer.org/) is full-featured database management tool written in PHP and it is part of this Sandbox.
To use it, browse to the subdirectory `/adminer` in your project root (i.e. `http://localhost:4000/adminer`).


License
-------
- Nette: New BSD License or GPL 2.0 or 3.0
- Adminer: Apache License 2.0 or GPL 2
