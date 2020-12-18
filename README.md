# ReCodEx Core and REST API

[![Build Status](https://github.com/ReCodEx/api/workflows/CI/badge.svg)](https://github.com/ReCodEx/api/actions)
[![API documentation](https://img.shields.io/badge/docs-OpenAPI-orange.svg)](https://recodex.github.io/api/)
[![codecov](https://codecov.io/gh/ReCodEx/api/branch/master/graph/badge.svg?token=LSWVLRLMH6)](https://codecov.io/gh/ReCodEx/api)
[![GitHub release](https://img.shields.io/github/release/recodex/api.svg)](https://github.com/ReCodEx/wiki/wiki/Changelog)

Business logic core of the application and a REST API that provides access to other modules.


## Installation

### Prerequisites

You need a web server with PHP (7.3+) and MySQL or MariaDB database.

We recommend installing PHP from remi repository:
```
# dnf install dnf-utils http://rpms.remirepo.net/enterprise/remi-release-8.rpm
```

You may list the PHP modules thusly:
```
# dnf module list php
```

...and select the right module:
```
# dnf module enable php:remi-7.3
```

If you install core-api as a package, the PHP will be installed as dependencies.
Otherwise, you may install it manually:
```
# dnf -y install php php-json php-mysqlnd php-ldap php-pecl-yaml php-pecl-zip php-pecl-zmq php-xml php-intl php-mbstring
```

It is also recommended that you create a separate database (e.g., `recodex`)
and a separate DB user who is granted all rights for this database (and only
this database).

You can do this by running `mysql -uroot -p` and then executing these SQLs:
```
CREATE DATABASE `recodex`;
CREATE USER 'recodex'@'localhost' IDENTIFIED BY 'someSecretPasswordYouNeedToSetYourself';
GRANT ALL PRIVILEGES ON `recodex`.* TO 'recodex'@'localhost';
```


### Install as Package

This is recommended way for CentOS/RHEL and Fedora systems.

```
# dnf copr enable semai/ReCodEx
# dnf install recodex-core
```

The module will be installed in `/opt/recodex-core`. After installation, you need to
edit `/etc/recodex/core-api/config.neon`. Proceed from step 3. of manual
installation to the end.


### Manual Installation

The web API requires a PHP runtime version at least 7.3. Which one depends on
actual configuration, there is a choice between _mod_php_ inside Apache,
_php-fpm_ with Apache or Nginx proxy or running it as standalone uWSGI script.
Also see the required PHP modules in the prerequisites section.

The API depends on some other projects and libraries which are managed by
[Composer](https://getcomposer.org/). It can be installed from system
repositories or downloaded from the website, where detailed instructions are as
well. Composer reads `composer.json` file in the project root and installs
dependencies to the `vendor/` subdirectory.

1. Clone the git repository
2. Run `composer install`
3. Create a database and fill in the access information in 
   `app/config/config.local.neon` (for an example, see 
   `app/config/config.local.neon.example`)
   do not forget to set the database configuration, especially the credentials.
4. Setup the database schema by running `bin/console migrations:migrate`


### Post-Install

You may optionally fill the database with initial values by running
`bin/console db:fill init`, after this database will contain:
* Instance with administrator registered as local account with credentials username: `admin@admin.com`, password: `admin`
* Runtime environments which ReCodEx can handle
* Default single hardware group which might be used for workers
* Pipelines for runtime environments which can be used when building exercises

Creating these data manually may be somewhat difficult and tedious, so it might
be a good idea to proceed with this step and perhaps prune the data later
(remove runtimes and pipelines you will not use).

Finally, you need to configure the web server, so that
1. Directory `/opt/recodex-core/www` is accessible and PHP scripts here are interpreted.
2. Proper alias is set for `/opt/recodex-core/www` that matches API path set in
   other modules, especially the web frontend (e.g., `/api` if the recodex runs
   on a root of a domain).
3. It might necessary to set up CORS headers (similar thing goes for the web
   app) as follows:
   
```
Header always set Access-Control-Allow-Origin "*"
Header always set Access-Control-Allow-Headers "headers, Authorization, Accept-Language, X-ReCodEx-lang"
Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, PATCH, OPTIONS"
```


## Configuration

The API can be configured in `config.neon` and `config.local.neon` files in
`app/config` directory of the API project source tree. The first file is
predefined by authors and should not be modified. The second one is not present
and could be created by copying `config.local.neon.example` template in the
config directory. Local configuration have higher precedence, so it will
override default values from `config.neon`.

Descriptions of particular config items are managed in static global config file in form of comments. Global `config.neon` should never be changed, local changes should be made to `config.local.neon`.

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
00	03	*	*	6	bin/console db:cleanup:uploads
15	03	*	*	6	bin/console db:cleanup:exercise-configs
30	03	*	*	6	bin/console db:cleanup:localized-texts
45	03	*	*	6	bin/console db:cleanup:pipeline-configs
00	04	*	*	*	bin/console notifications:assignment-deadlines
```

The example above will send email notifications at 4 a.m. every day and perform database garbage collection tasks between 3 and 4 a.m. every Saturday. 

Some details of these periodic commands (e.g., a threshold period of relevant assignment deadlines) can be configured in api neon config file.


## Adminer

[Adminer](https://www.adminer.org/) is full-featured database management tool written in PHP and it is part of this Sandbox.
To use it, browse to the subdirectory `/adminer` in your project root (i.e. `http://localhost:4000/adminer`).

## Documentation

Feel free to read the documentation on [our wiki](https://github.com/ReCodEx/wiki/wiki).

## License

- ReCodEx REST API: _MIT License_
- Nette: _New BSD License or GPL 2.0 or 3.0_
- Adminer: _Apache License 2.0 or GPL 2_
