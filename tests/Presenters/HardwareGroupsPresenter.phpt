<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\V1Module\Presenters\HardwareGroupsPresenter;
use App\V1Module\Presenters\UsersPresenter;
use Tester\Assert;
use App\Model\Repository\HardwareGroups;
use App\Model\Entity\HardwareGroup;

/**
 * @httpCode any
 * @testCase
 */
class TestHardwareGroupsPresenter extends Tester\TestCase
{
  /** @var UsersPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

  /** @var Nette\Security\User */
  private $user;

  /** @var string */
  private $presenterPath = "V1:HardwareGroups";

  /** @var HardwareGroups */
  protected $hardwareGroups;

  /** @var  Nette\DI\Container */
  protected $container;

  public function __construct()
  {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
    $this->hardwareGroups = $container->getByType(HardwareGroups::class);
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);
    $this->presenter = PresenterTestHelper::createPresenter($this->container, HardwareGroupsPresenter::class);
  }

  protected function tearDown()
  {
    Mockery::close();

    if ($this->user->isLoggedIn()) {
      $this->user->logout(TRUE);
    }
  }

  public function testGetAllHwGroups()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    $request = new Nette\Application\Request($this->presenterPath, 'GET', ['action' => 'default']);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(count($this->hardwareGroups->findAll()), $result['payload']);

    foreach ($result['payload'] as $hwGroup) {
      Assert::type(HardwareGroup::class, $hwGroup);
      Assert::contains($hwGroup, $this->hardwareGroups->findAll());
    }
  }

}

$testCase = new TestHardwareGroupsPresenter();
$testCase->run();
