<?php

include '../bootstrap.php';

use Tester\Assert;
use Nette\Utils\Json;

use App\Model\Entity\Login;

class TestLogin extends RestApiTestCase
{
    // @todo - do not use Guzzle for God's sake... (it does not work on Travis)
}

define('TEMP_DIR', __DIR__ . "/../temp");

# Testing methods run
$testCase = new TestLogin;
$testCase->run();
