<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\V1Module\Presenters\SubmissionsPresenter;
use Tester\Assert;


class TestSubmissionsPresenter extends Tester\TestCase
{
  private $adminLogin = "admin@admin.com";
  private $adminPassword = "admin";

  /** @var SubmissionsPresenter */
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
    $this->presenter = PresenterTestHelper::createPresenter($this->container, SubmissionsPresenter::class);
    /*$this->presenter->evaluations = Mockery::mock('App\Model\Repository\SolutionEvaluations')
        ->shouldReceive('persist')->with(Mockery::any())->getMock();*/
    /*$this->presenter->evaluationLoader = Mockery::mock('App\Helpers\EvaluationLoader')
        ->shouldReceive('load')
        ->withAnyArgs()
        ->andReturn(Mockery::mock('App\Model\Entity\SolutionEvaluation'))
        ->getMock();*/
  }

  protected function tearDown()
  {
    Mockery::close();

    if ($this->user->isLoggedIn()) {
      $this->user->logout(TRUE);
    }
  }

  public function testGetAllSubmissions()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);

    $request = new Nette\Application\Request('V1:Submissions', 'GET', ['action' => 'default']);
    $response = $this->presenter->run($request);
    Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    $allResults = $result['payload'];
    Assert::equal(2, count($allResults));
    $theResult = array_pop($allResults);
    Assert::equal('Random note', $theResult->note);
  }

  public function testGetEvaluation()
  {
    $token = PresenterTestHelper::login($this->container, "submitUser1@example.com", "password");

    $allSubmissions = $this->presenter->submissions->findAll();
    $submission = array_pop($allSubmissions);

    $request = new Nette\Application\Request('V1:Submissions',
        'GET',
        ['action' => 'evaluation', 'id' => $submission->id]
    );
    $response = $this->presenter->run($request);
    Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));

    // Check invariants
    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::same($submission->getId(), $result['payload']['id']);
  }

  public function testSetBonusPoints()
  {
    $token = PresenterTestHelper::login($this->container, "admin@admin.com", "admin");

    $allSubmissions = $this->presenter->submissions->findAll();
    $submission = array_pop($allSubmissions);

    $request = new Nette\Application\Request('V1:Submissions',
      'POST',
      ['action' => 'setBonusPoints', 'id' => $submission->id],
      ['bonusPoints' => 4]
    );
    $response = $this->presenter->run($request);
    Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));

    // Check invariants
    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal("OK", $result['payload']);

    $submission = $this->presenter->submissions->get($submission->id);
    Assert::equal(4, $submission->getEvaluation()->getBonusPoints());
  }

  public function testDownloadResultArchive()
  {
    PresenterTestHelper::login($this->container, "admin@admin.com", "admin");
    $submission = current($this->presenter->submissions->findAll());

    // mock everything you can
    $mockGuzzleStream = Mockery::mock(Psr\Http\Message\StreamInterface::class);
    $mockGuzzleStream->shouldReceive("getSize")->andReturn(0);
    $mockGuzzleStream->shouldReceive("eof")->andReturn(true);

    $mockProxy = Mockery::mock(App\Helpers\FileServerProxy::class);
    $mockProxy->shouldReceive("getResultArchiveStream")->withAnyArgs()->andReturn($mockGuzzleStream);
    $this->presenter->fileServerProxy = $mockProxy;

    $request = new Nette\Application\Request('V1:Submissions',
      'GET',
      ['action' => 'downloadResultArchive', 'id' => $submission->id]
    );
    $response = $this->presenter->run($request);
    Assert::same(App\Responses\GuzzleResponse::class, get_class($response));

    // Check invariants
    Assert::equal($submission->getId() . '.zip', $response->getName());
  }

}

$testCase = new TestSubmissionsPresenter();
$testCase->run();