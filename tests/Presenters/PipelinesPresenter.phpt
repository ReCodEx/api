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


  public function testGetDefaultBoxes()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    $allBoxes = $this->presenter->boxService->getAllBoxes();

    $request = new Nette\Application\Request('V1:Pipelines',
      'GET',
      ['action' => 'getDefaultBoxes']
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal($allBoxes, $result['payload']);
    Assert::count(count($allBoxes), $result['payload']);
  }

  public function testGetAllPipelines()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $request = new Nette\Application\Request('V1:Pipelines',
      'GET',
      ['action' => 'getPipelines']
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::same($this->presenter->pipelines->findAll(), $result['payload']);
    Assert::count(count($this->presenter->pipelines->findAll()), $result['payload']);
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
    Assert::same($pipeline, $payload);
  }

  public function testCreatePipeline()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    $pipelines = $this->presenter->pipelines->findAll();
    $pipelinesCount = count($pipelines);

    $request = new Nette\Application\Request('V1:Pipelines', 'POST', ['action' => 'createPipeline']);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    $payload = $result['payload'];
    Assert::equal(200, $result['code']);
    Assert::count($pipelinesCount + 1, $this->presenter->pipelines->findAll());

    Assert::type(\App\Model\Entity\Pipeline::class, $payload);
    Assert::equal(PresenterTestHelper::ADMIN_LOGIN, $payload->getAuthor()->email);
    Assert::equal("Pipeline by " . $this->user->identity->getUserData()->getName(), $payload->getName());
  }

  public function testUpdatePipeline()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $pipeline = current($this->presenter->pipelines->findAll());
    $pipelineConfig = [
      [
        'name' => 'infile',
        'type' => 'data',
        'portsIn' => [],
        'portsOut' => ['in_file' => 'in_data_file']
      ],
      [
        'name' => 'judgement',
        'type' => 'judge-normal',
        'portsIn' => ['in_file' => 'in_data_file'],
        'portsOut' => ['score' => 'judge_score']
      ]
    ];

    $request = new Nette\Application\Request('V1:Pipelines',
      'POST',
      ['action' => 'updatePipeline', 'id' => $pipeline->getId()],
      [
        'name' => 'new pipeline name',
        'pipeline' => $pipelineConfig
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    $payload = $result['payload'];
    Assert::equal(200, $result['code']);

    Assert::equal('new pipeline name', $payload->getName());
    Assert::equal($pipelineConfig, $payload->getPipelineConfig()->getParsedPipeline());

    $parsedPipeline = $payload->getPipelineConfig()->getParsedPipeline();
    Assert::equal("infile", $parsedPipeline[0]["name"]);
    Assert::equal("judgement", $parsedPipeline[1]["name"]);
    Assert::equal("data", $parsedPipeline[0]["type"]);
    Assert::equal("judge-normal", $parsedPipeline[1]["type"]);
  }

}

$testCase = new TestPipelinesPresenter();
$testCase->run();
