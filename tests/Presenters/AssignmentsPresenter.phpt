<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use Tester\Assert;

class TestAssignmentsPresenter extends Tester\TestCase
{
  use MockeryTrait;

  /** @var App\V1Module\Presenters\AssignmentsPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

  /** @var  Nette\DI\Container */
  protected $container;

  public function __construct()
  {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
  }

  protected function setUp()
  {
    parent::setUp();

    PresenterTestHelper::fillDatabase($this->container);

    $this->presenter = $this->container->getByType(\App\V1Module\Presenters\AssignmentsPresenter::class);
    $this->presenter->autoCanonicalize = FALSE;
    $this->presenter->submissionHelper = Mockery::mock(App\Helpers\SubmissionHelper::class);
    $this->presenter->monitorConfig = new App\Helpers\MonitorConfig(['address' => 'localhost']);
  }

  public function testListAssignments()
  {
    $token = PresenterTestHelper::login($this->container, "admin@admin.com", "admin");
    PresenterTestHelper::setToken($this->presenter, $token);
    $request = new Nette\Application\Request('V1:Assignments', 'GET', ['action' => 'default']);
    $this->presenter->run($request);

    Assert::true(TRUE);
  }
}

$testCase = new TestAssignmentsPresenter();
$testCase->run();