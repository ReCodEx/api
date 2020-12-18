<?php

// Enable CORS for the API server
header("Access-Control-Allow-Origin: *");
$acReqHeaders = (!empty($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
    ? $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']
    : "headers, Authorization, Accept-Language, Content-Type, X-ReCodEx-lang";
header("Access-Control-Allow-Headers: $acReqHeaders");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");
unset($acReqHeaders);  // Make sure we do not leave any mess at global scope

// The OPTIONS request should have been stopped at Apache configuration level, but if it was not ...
if (!empty($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) == 'OPTIONS') {
    exit;
}


// Uncomment this line if you must temporarily take down your site for maintenance.
// require __DIR__ . '/.maintenance.php';

require __DIR__ . '/../vendor/autoload.php';

$configurator = App\Bootstrap::boot();
$container = $configurator->createContainer();
$application = $container->getByType(Nette\Application\Application::class);
$application->run();
