<?php

// Enable CORS for the API server
header("Access-Control-Allow-Origin: *");
$headers = getallheaders();
header("Access-Control-Allow-Headers: " . (
  !empty($headers['Access-Control-Request-Headers'])
    ? $headers['Access-Control-Request-Headers']
    : "headers, Authorization, Accept-Language"));
header("Access-Control-Allow-Origin: GET, POST, PUT, DELETE, PATCH, OPTIONS");
unset($headers);  // Make sure we do not leave any mess at global scope

// The OPTIONS request should have been stopped at Apache configuration level, but if it was not ...
if (strtoupper($_SERVER['REQUEST_METHOD']) == 'OPTIONS') exit;


// Uncomment this line if you must temporarily take down your site for maintenance.
// require __DIR__ . '/.maintenance.php';

$container = require __DIR__ . '/../app/bootstrap.php';
$container->getByType(Nette\Application\Application::class)->run();
