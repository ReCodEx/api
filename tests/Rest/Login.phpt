<?php

require '../bootstrap.php';

use Tester\Assert;
use Nette\Utils\Json;

use App\Model\Entity\Login;


class TestLogin extends RestApiTestCase
{
  private $users = [
    [
      "email" => "a@a.com",
      "firstName" => "Adolf",
      "lastName" => "Almighty",
      "password" => "*1&2$3456"
    ],
    [
      "email" => "b@b.com",
      "firstName" => "Bob",
      "lastName" => "Brutal",
      "password" => "*1&2$3455"
    ],
    [
      "email" => "c@c.com",
      "firstName" => "Cedrik",
      "lastName" => "Courageous",
      "password" => "*1&2$3454"
    ],
  ];

  private $instance;

  public function testApiWorking() {
    $response = $this->get("/");
    Assert::same(200, $response->statusCode);
    Assert::same(TRUE, isset($response->body));
    Assert::same($response->body->project, 'ReCodEx API');
  }

  /**
   * Try login for all testing users, if some doesn't exist, create them (which also
   * logs them in)
   */
  public function testCreateAndLoginUsers() {
    for ($i=0; $i < count($this->users); $i++) { 
      $user = $this->users[$i];
      $response = $this->login($user);

      if ($response->statusCode == 400) { // user does not exist, create him
        $response = $this->post("/users", [
          'form_params' => $user
        ]);
        Assert::same(201, $response->statusCode);
      } else {
        Assert::same(200, $response->statusCode);
      }
    }
  }

  public function testCreateInstance() {
    $user = $this->users[1];
    $this->login($user);

    $instanceValues = [
      'name' => "Some instance",
      'description' => "Some description",
      'isOpen' => TRUE,
    ];

    $response = $this->post("/instances", [
      'form_params' => $instanceValues
    ]);
    
    Assert::same(201, $response->statusCode);
    Assert::true(isset($response->body->payload));
    $instance = $response->body->payload;

    $this->validateInstance($instance, $instanceValues);

    $this->instance = $instance;
  }
  
  protected function validateInstance($instance, $expected) {
    Assert::true(isset($instance->id));
    Assert::true(isset($instance->name));
    Assert::true(isset($instance->description));
    Assert::true(isset($instance->isOpen));
    Assert::true(isset($instance->isAllowed));
    Assert::true(isset($instance->createdAt));
    Assert::true(isset($instance->updatedAt));

    foreach ($expected as $key => $value) {
      Assert::same($value, $instance->$key);
    }
  }

  public function testUpdateInstance() {
    $instanceValues = [
      'name' => "Some different instance",
      'description' => "",
      'isOpen' => FALSE,
    ];
    $response = $this->put("/instances/" . $this->instance->id, [
      'form_params' => $instanceValues
    ]);
    
    Assert::same(200, $response->statusCode);
    Assert::true(isset($response->body->payload));
    $instance = $response->body->payload;

    print_r($response->body);

    $this->validateInstance($instance, $instanceValues);

    $this->instance = $instance;
  }

  public function testDeleteInstance() {
    $response = $this->delete("/instances/" . $this->instance->id);
    Assert::same(200, $response->statusCode);
    Assert::true(isset($response->body->payload));
    Assert::true(empty($response->body->payload));
  }
}

# Testing methods run
$testCase = new TestLogin;
$testCase->run();