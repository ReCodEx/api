#!/usr/bin/env php
<?php declare(strict_types=1);
use PHPStan\Command\AnalyseCommand;

require_once __DIR__ . '/../vendor/autoload.php';

gc_disable(); // performance boost

$application = new \Symfony\Component\Console\Application('PHPStan - PHP Static Analysis Tool');
$application->setCatchExceptions(false);
$application->add(new AnalyseCommand());
$application->run();
