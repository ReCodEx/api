<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\V1Module\Presenters\RuntimeEnvironmentsPresenter;
use App\V1Module\Presenters\UsersPresenter;
use Tester\Assert;
use App\Model\Repository\RuntimeEnvironments;
use App\Model\Entity\RuntimeEnvironment;

/**
 * @httpCode any
 * @testCase
 */
class TestRuntimeEnvironmentsPresenter extends Tester\TestCase
{
  /** @var UsersPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

  /** @var Nette\Security\User */
  private $user;

  /** @var string */
  private $presenterPath = "V1:RuntimeEnvironments";

  /** @var RuntimeEnvironments */
  protected $runtimeEnvironments;

  /** @var  Nette\DI\Container */
  protected $container;

  public function __construct()
  {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
    $this->runtimeEnvironments = $container->getByType(RuntimeEnvironments::class);
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);
    $this->presenter = PresenterTestHelper::createPresenter($this->container, RuntimeEnvironmentsPresenter::class);
  }

  protected function tearDown()
  {
    Mockery::close();

    if ($this->user->isLoggedIn()) {
      $this->user->logout(TRUE);
    }
  }

  public function testGetAllEnvironments()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    $request = new Nette\Application\Request($this->presenterPath, 'GET', ['action' => 'default']);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(count($this->runtimeEnvironments->findAll()), $result['payload']);

    foreach ($result['payload'] as $environment) {
      Assert::type(RuntimeEnvironment::class, $environment);
      Assert::contains($environment, $this->runtimeEnvironments->findAll());
    }
  }

}

$testCase = new TestRuntimeEnvironmentsPresenter();
$testCase->run();
