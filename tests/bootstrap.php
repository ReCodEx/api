<?php

const TEMP_DIR = __DIR__ . '/../temp';
require __DIR__ . '/../vendor/autoload.php';
set_time_limit(300);

Tester\Environment::setup();

$appDir = __DIR__ . '/../app';

$configurator = new Nette\Configurator();
$configurator->setDebugMode(false);
$configurator->setTempDirectory(TEMP_DIR);
$configurator->addParameters(['appDir' => $appDir]);

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
