<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\NotFoundException;
use App\Helpers\Notifications\AssignmentPointsEmailsSender;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\User;
use App\V1Module\Presenters\AssignmentSolutionsPresenter;
use Tester\Assert;


/**
 * @testCase
 */
class TestAssignmentSolutionsPresenter extends Tester\TestCase
{
  /** @var AssignmentSolutionsPresenter */
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
    $this->em = PresenterTestHelper::getEntityManager($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);
    $this->presenter = PresenterTestHelper::createPresenter($this->container, AssignmentSolutionsPresenter::class);
  }

  protected function tearDown()
  {
    Mockery::close();

    if ($this->user->isLoggedIn()) {
      $this->user->logout(true);
    }
  }


  public function testGetSolution()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);
    $solution = current($this->presenter->assignmentSolutions->findAll());

    $request = new Nette\Application\Request('V1:AssignmentSolutions',
      'GET',
      ['action' => 'solution', 'id' => $solution->id]
    );
    $response = $this->presenter->run($request);
    Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));

    // Check invariants
    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::same($solution->getId(), $result['payload']['id']);
  }

  public function testGetEvaluations()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);
    $solution = current($this->presenter->assignmentSolutions->findAll());

    $request = new Nette\Application\Request('V1:AssignmentSolutions',
      'GET',
      ['action' => 'evaluations', 'id' => $solution->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));

    // Check invariants
    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    $payload = $result['payload'];
    Assert::count($solution->getSubmissions()->count(), $payload);
  }

  public function testGetEvaluation()
  {
    $token = PresenterTestHelper::login($this->container, "submitUser1@example.com", "password");

    $allSubmissions = $this->presenter->assignmentSolutionSubmissions->findAll();
    $submission = array_pop($allSubmissions);

    $request = new Nette\Application\Request('V1:AssignmentSolutions',
        'GET',
        ['action' => 'evaluation', 'evaluationId' => $submission->id]
    );
    $response = $this->presenter->run($request);
    Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));

    // Check invariants
    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::same($submission->getId(), $result['payload']['id']);
  }

  public function testDeleteEvaluation()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    $allSubmissions = $this->presenter->assignmentSolutionSubmissions->findAll();
    $submission = reset($allSubmissions);
    $submissionsCount = count($allSubmissions);

    $payload = PresenterTestHelper::performPresenterRequest($this->presenter, 'V1:AssignmentSolutions', 'DELETE', [
      'action' => 'deleteEvaluation', 'evaluationId' => $submission->getId() ]);

    $remainingSubmissions = $this->presenter->assignmentSolutionSubmissions->findAll();
    Assert::count($submissionsCount-1, $remainingSubmissions);
    Assert::notContains($submission->getId(), array_map(function($eval) { return $eval->getId(); }, $remainingSubmissions));
  }


  public function testSetBonusPoints()
  {
    $token = PresenterTestHelper::login($this->container, "admin@admin.com", "admin");
    $solution = current($this->presenter->assignmentSolutions->findAll());

    /** @var Mockery\Mock | AssignmentPointsEmailsSender $mockPointsEmailsSender */
    $mockPointsEmailsSender = Mockery::mock(AssignmentPointsEmailsSender::class);
    $mockPointsEmailsSender->shouldReceive("assignmentPointsUpdated")->with($solution)->andReturn(true)->once();
    $this->presenter->assignmentPointsEmailsSender = $mockPointsEmailsSender;

    $request = new Nette\Application\Request('V1:AssignmentSolutions',
      'POST',
      ['action' => 'setBonusPoints', 'id' => $solution->id],
      ['bonusPoints' => 4, 'overriddenPoints' => 857]
    );
    $response = $this->presenter->run($request);
    Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));

    // Check invariants
    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal("OK", $result['payload']);

    $solution = $this->presenter->assignmentSolutions->get($solution->id);
    Assert::equal(4, $solution->getBonusPoints());
    Assert::equal(857, $solution->getOverriddenPoints());
  }

  public function testSetAcceptedSubmission()
  {
    $allSubmissions = $this->presenter->assignmentSolutions->findAll();
    /** @var AssignmentSolution $submission */
    $submission = array_pop($allSubmissions);
    $assignment = $submission->getAssignment();

    $user = $assignment->getGroup()->getSupervisors()->filter(
      function (User $user) use ($submission) {
        return $submission->getSolution()->getAuthor() !== $user && $user->getRole() !== 'superadmin';
      })->first();

    Assert::notSame(null, $user);

    PresenterTestHelper::login($this->container, $user->getEmail());

    $request = new Nette\Application\Request('V1:AssignmentSolutions',
      'POST',
      ['action' => 'setAcceptedSubmission', 'id' => $submission->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::same(Nette\Application\Responses\ForwardResponse::class, get_class($response));

    // Check invariants
    $submission = $this->presenter->assignmentSolutions->get($submission->getId());
    Assert::true($submission->isAccepted());
  }

  public function testUnsetAcceptedSubmission()
  {
    $allSubmissions = $this->presenter->assignmentSolutions->findAll();
    /** @var AssignmentSolution $submission */
    $submission = array_pop($allSubmissions);
    $assignment = $submission->getAssignment();

    // set accepted flag
    $submission->setAccepted(true);
    $this->presenter->assignmentSolutions->flush();
    Assert::true($submission->getAccepted());

    $user = $assignment->getGroup()->getSupervisors()->filter(
      function (User $user) use ($submission) {
        return $submission->getSolution()->getAuthor() !== $user && $user->getRole() !== 'superadmin';
      })->first();
    Assert::notSame(null, $user);

    PresenterTestHelper::login($this->container, $user->getEmail());

    $request = new Nette\Application\Request('V1:AssignmentSolutions',
      'DELETE',
      ['action' => 'unsetAcceptedSubmission', 'id' => $submission->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::same(Nette\Application\Responses\ForwardResponse::class, get_class($response));

    // Check invariants
    $submission = $this->presenter->assignmentSolutions->get($submission->getId());
    Assert::false($submission->isAccepted());
  }

  public function testDownloadSolutionArchive()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);
    $solution = current($this->presenter->assignmentSolutions->findAll());

    $request = new Nette\Application\Request('V1:AssignmentSolutions',
      'GET',
      ['action' => 'downloadSolutionArchive', 'id' => $solution->id]
    );
    $response = $this->presenter->run($request);
    Assert::same(App\Responses\ZipFilesResponse::class, get_class($response));

    // Check invariants
    Assert::equal("solution-" . $solution->getId() . '.zip', $response->getName());
  }

  public function testDownloadResultArchive()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);
    $submission = current($this->presenter->assignmentSolutionSubmissions->findAll());

    // mock everything you can
    $mockGuzzleStream = Mockery::mock(Psr\Http\Message\StreamInterface::class);
    $mockGuzzleStream->shouldReceive("getSize")->andReturn(0);
    $mockGuzzleStream->shouldReceive("eof")->andReturn(true);

    $mockProxy = Mockery::mock(App\Helpers\FileServerProxy::class);
    $mockProxy->shouldReceive("getFileserverFileStream")->withAnyArgs()->andReturn($mockGuzzleStream);
    $this->presenter->fileServerProxy = $mockProxy;

    $request = new Nette\Application\Request('V1:AssignmentSolutions',
      'GET',
      ['action' => 'downloadResultArchive', 'evaluationId' => $submission->id]
    );
    $response = $this->presenter->run($request);
    Assert::same(App\Responses\GuzzleResponse::class, get_class($response));

    // Check invariants
    Assert::equal("results-" . $submission->getId() . '.zip', $response->getName());
  }

  public function testDeleteAssignmentSolution()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    /** @var AssignmentSolution $solution */
    $solution = current($this->presenter->assignmentSolutions->findAll());
    $solutionId = $solution->getId();

    $request = new Nette\Application\Request('V1:AssignmentSolutions', 'DELETE', [
      'action' => 'deleteSolution',
      'id' => $solution->getId()
    ]);

    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal('OK', $result['payload']);

    Assert::exception(function () use ($solutionId) {
      $this->presenter->assignmentSolutions->findOrThrow($solutionId);
    }, NotFoundException::class);
  }

}

(new TestAssignmentSolutionsPresenter())->run();
