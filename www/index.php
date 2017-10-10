<?php

// Enable CORS for the API server
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: headers, Authorization, Accept-Language");

// Uncomment this line if you must temporarily take down your site for maintenance.
require __DIR__ . '/.maintenance.php';

$container = require __DIR__ . '/../app/bootstrap.php';
$container->getByType(Nette\Application\Application::class)->run();
