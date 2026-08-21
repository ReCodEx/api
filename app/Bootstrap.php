<?php

namespace App;

use Nette\Configurator;
use Tracy\Debugger;

class Bootstrap
{
    public static function boot(): Configurator
    {
        $configurator = new Configurator;

        //$configurator->setDebugMode('secret@23.75.345.200'); // enable for your remote IP
        // $configurator->setDebugMode(false);
        $configurator->enableTracy(__DIR__ . '/../log');
        Debugger::$strictMode = E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED;

        $configurator->setTimeZone('Europe/Prague');
        $configurator->setTempDirectory(__DIR__ . '/../temp');

        $configurator->createRobotLoader()
            ->addDirectory(__DIR__)
            ->register();

        $configurator->addConfig(__DIR__ . '/config/config.neon');
        if (file_exists(__DIR__ . '/config/config.local.neon')) {
            $configurator->addConfig(__DIR__ . '/config/config.local.neon');
        }

        return $configurator;
    }
}
