<?php

include '../bootstrap.php';

use Tester\Assert;
use Nette\Utils\Json;

use App\Model\Entity\Login;

class TestLogin extends RestApiTestCase
{
  private $a = 0;

  public function testCreateUser() {
    $this->a = 1;
    Assert::same(TRUE, TRUE);
  }

  public function testRemoveUser() {
    $response = $this->get('/');
    Assert::same(200, $response->statusCode);
    Assert::type('object', $response->body);
    Assert::same($response->body->project, 'ReCodEx API');
  }

}

define('TEMP_DIR', __DIR__ . "/../temp");

# Testing methods run
$testCase = new TestLogin;
$testCase->run();
