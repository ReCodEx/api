<?php

use App\V1Module\Presenters\SecurityPresenter;
use Nette\Application\Request;
use Nette\Application\Responses\JsonResponse;
use Nette\DI\Container;
use Tester\Assert;

$container = require_once __DIR__ . "/../bootstrap.php";

/**
 * @testCase
 */
class TestSecurityPresenter extends Tester\TestCase {
  /**
   * @var Container
   */
  private $container;

  /**
   * @var SecurityPresenter
   */
  private $presenter;

  private $argv;

  public function __construct(Container $container) {
    $this->container = $container;
  }

  protected function setUp() {
    $this->presenter = PresenterTestHelper::createPresenter($this->container, SecurityPresenter::class);
    PresenterTestHelper::fillDatabase($this->container);
    $this->argv = $_SERVER["argv"];
    $_SERVER["argv"] = null;
  }

  protected function tearDown() {
    $_SERVER["argv"] = $this->argv;
  }

  public function testAllowed() {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    $response = $this->presenter->run(new Request("V1:Security", "POST", [
      "action" => "check"
    ], [
      "url" => "/v1/exercises",
      "method" => "GET"
    ]));

    Assert::type(JsonResponse::class, $response);
    $payload = $response->getPayload()["payload"];

    Assert::true($payload["result"]);
    Assert::true($payload["isResultReliable"]);
  }

  public function testDisabled() {
    PresenterTestHelper::login($this->container, PresenterTestHelper::STUDENT_GROUP_MEMBER_LOGIN);

    $response = $this->presenter->run(new Request("V1:Security", "POST", [
      "action" => "check"
    ], [
      "url" => "/v1/exercises",
      "method" => "GET"
    ]));

    Assert::type(JsonResponse::class, $response);
    $payload = $response->getPayload()["payload"];

    Assert::false($payload["result"]);
    Assert::true($payload["isResultReliable"]);
  }
}

(new TestSecurityPresenter($container))->run();
