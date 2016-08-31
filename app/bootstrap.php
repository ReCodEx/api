<?php

require __DIR__ . '/../vendor/autoload.php';

$configurator = new Nette\Configurator;

$configurator->enableDebugger(__DIR__ . '/../log');
$configurator->setDebugMode(FALSE);

$configurator->setTimeZone('Europe/Prague');
$configurator->setTempDirectory(__DIR__ . '/../temp');

$configurator->createRobotLoader()
	->addDirectory(__DIR__)
	->register();

$configurator->addConfig(__DIR__ . '/config/config.neon');

// local config is not needed for Travis or production server,
// but is crutial for development - include only when provided
if (file_exists(__DIR__ . '/config/config.local.neon')) {
  $configurator->addConfig(__DIR__ . '/config/config.local.neon');
}

$container = $configurator->createContainer();

return $container;
