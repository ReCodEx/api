<?php

// Enable CORS for the API server
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: headers, Authorization, Accept-Language");

$container = require __DIR__ . '/../app/bootstrap.php';
$container->getByType(Nette\Application\Application::class)->run();
