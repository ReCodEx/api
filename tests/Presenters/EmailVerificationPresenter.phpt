<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\V1Module\Presenters\EmailVerificationPresenter;
use Tester\Assert;

class TestEmailVerificationPresenter extends Tester\TestCase
{
  /** @var GroupsPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

  /** @var  Nette\DI\Container */
  protected $container;

  /** @var Nette\Security\User */
  private $user;

  /** @var \App\Security\AccessManager */
  private $accessManager;

  public function __construct()
  {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
    $this->accessManager = $container->getByType(\App\Security\AccessManager::class);
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);
    $this->presenter = PresenterTestHelper::createPresenter($this->container, EmailVerificationPresenter::class);
  }

  protected function tearDown()
  {
    if ($this->user->isLoggedIn()) {
      $this->user->logout(TRUE);
    }
  }

  public function testListAllExercises()
  {
    Assert::equal(TRUE, TRUE);
  }
}

$testCase = new TestEmailVerificationPresenter();
$testCase->run();
