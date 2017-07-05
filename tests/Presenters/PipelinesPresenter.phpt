<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\V1Module\Presenters\PipelinesPresenter;
use Tester\Assert;

class TestPipelinesPresenter extends Tester\TestCase
{
  /** @var PipelinesPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

  /** @var  Nette\DI\Container */
  protected $container;

  /** @var Nette\Security\User */
  private $user;

  public function __construct()
  {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);
    $this->presenter = PresenterTestHelper::createPresenter($this->container, PipelinesPresenter::class);
  }

  protected function tearDown()
  {
    Mockery::close();

    if ($this->user->isLoggedIn()) {
      $this->user->logout(TRUE);
    }
  }

  public function testGetPipeline()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $pipeline = current($this->presenter->pipelines->findAll());

    $request = new Nette\Application\Request('V1:Pipelines',
      'GET',
      ['action' => 'getPipeline', 'id' => $pipeline->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    $payload = $result['payload'];


    // @todo
    Assert::true(false);
  }

}

$testCase = new TestPipelinesPresenter();
$testCase->run();
