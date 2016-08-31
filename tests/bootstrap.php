<?php

require __DIR__ . '/../vendor/autoload.php';

Tester\Environment::setup();

$configurator = new Nette\Configurator;
$configurator->setDebugMode(FALSE);
$configurator->setTempDirectory(__DIR__ . '/../temp');
$configurator->createRobotLoader()
	->addDirectory(__DIR__ . '/../app')
  ->addDirectory(__DIR__ . '/base')
	->register();

$configurator->addConfig(__DIR__ . '/../app/config/config.neon');
if (file_exists(__DIR__ . '/../app/config/config.local.neon')) {
  $configurator->addConfig(__DIR__ . '/../app/config/config.local.neon');
}

return $configurator->createContainer();
