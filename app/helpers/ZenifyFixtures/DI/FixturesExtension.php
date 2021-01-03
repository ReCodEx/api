<?php

declare(strict_types=1);

/*
 * This file is part of Zenify
 * Copyright (c) 2012 Tomas Votruba (http://tomasvotruba.cz)
 */

namespace Zenify\DoctrineFixtures\DI;

use Faker\Generator;
use Nelmio\Alice\Faker\Provider\AliceProvider;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;

final class FixturesExtension extends CompilerExtension
{

    /**
     * @var array
     */
    private $defaults = [
        'locale' => 'cs_CZ',
        'seed' => 1
    ];


    public function loadConfiguration()
    {
        $services = $this->loadFromFile(__DIR__ . '/services.neon');
        $this->compiler->loadDefinitionsFromConfig($services['services']);
    }


    public function beforeCompile()
    {
        $containerBuilder = $this->getContainerBuilder();
        $containerBuilder->resolve();

        $this->loadFakerConfiguration();
    }


    private function loadFakerConfiguration()
    {
        $this->setConfig($this->validateConfig($this->defaults));
        $config = $this->getConfig();

        $this->getDefinitionByType(Generator::class)
            ->setArgument('locale', $config['locale'])
            ->addSetup('seed', [$config['seed']])
            ->addSetup('addProvider', [new AliceProvider()]);
    }


    private function getDefinitionByType(string $type): ServiceDefinition
    {
        $containerBuilder = $this->getContainerBuilder();
        /** @var ServiceDefinition $definition */
        $definition = $containerBuilder->getDefinition($containerBuilder->getByType($type));
        return $definition;
    }
}
