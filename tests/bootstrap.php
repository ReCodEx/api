<?php

const TEMP_DIR = __DIR__ . '/../temp';
require __DIR__ . '/../vendor/autoload.php';
set_time_limit(300);

Tester\Environment::setup();

// We have no control over the errors in imported modules (vendor directory),
// so we ignore deprecated warnings from there
$__previousErrorHandler = set_error_handler(
    function (int $severity, string $message, string $file, int $line) use (&$__previousErrorHandler) {
        if (($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) && str_contains($file, '/vendor/')) {
            return true;
        }
        return ($__previousErrorHandler) ? $__previousErrorHandler($severity, $message, $file, $line) : false;
    }
);

// and we need to make the TestCase::run ignore deprecated warnings too
// (otherwise it silently tears down the test in the middle, which is hard to debug)
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

$appDir = __DIR__ . '/../app';

$configurator = new Nette\Bootstrap\Configurator();
$configurator->setDebugMode(false);
$configurator->setTempDirectory(TEMP_DIR);
$configurator->addStaticParameters(['appDir' => $appDir]);

$configurator->createRobotLoader()
    ->addDirectory($appDir)
    ->addDirectory(__DIR__ . '/base')
    ->addDirectory(__DIR__ . '/Authorizator')
    ->register();

$configurator->addConfig(__DIR__ . '/../app/config/config.neon');

if (getenv("TRAVIS")) {
    $configurator->addConfig(__DIR__ . '/config.travis.neon');
} else {
    $configurator->addConfig(__DIR__ . '/config.tests.neon');
}

return $configurator->createContainer();
