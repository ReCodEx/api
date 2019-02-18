<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Console\RemoveInactiveUsers;
use App\Model\Repository\UploadedFiles;
use App\Model\Repository\Users;
use App\Model\Entity\UploadedFile;
use App\Helpers\AnonymizationHelper;
use Tester\Assert;


/**
 * @testCase
 */
class TestRemoveInactiveUsers extends Tester\TestCase
{
  /** @var RemoveInactiveUsers */
  protected $command;

  /** @var Nette\DI\Container */
  private $container;

  /** @var Users */
  private $users;

  public function __construct()
  {
    global $container;
    $this->container = $container;
    $this->users = $container->getByType(Users::class);
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);
    $this->command = new RemoveInactiveUsers("1 month", $this->users, $this->container->getByType(AnonymizationHelper::class));
  }

  protected function tearDown()
  {
    Mockery::close();
  }

  public function testCleanup()
  {
    $user = current($this->users->findAll());
    $user->updateLastAuthenticationAt(); // make sure this user is active
    $this->users->flush();

    $this->command->run(
      new Symfony\Component\Console\Input\StringInput("--silent"),
      new Symfony\Component\Console\Output\NullOutput()
    );

    Assert::count(1, $this->users->findAll());
    Assert::equal($user->getId(), current($this->users->findAll())->getId());
  }
}

$testCase = new TestRemoveInactiveUsers();
$testCase->run();
