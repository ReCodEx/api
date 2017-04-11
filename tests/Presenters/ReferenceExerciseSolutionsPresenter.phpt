<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Model\Entity\Exercise;
use App\Model\Entity\ReferenceSolutionEvaluation;
use App\Model\Repository\Exercises;
use App\Model\Repository\ReferenceExerciseSolutions;
use App\V1Module\Presenters\ReferenceExerciseSolutionsPresenter;
use Tester\Assert;
use App\Helpers\JobConfig;

class TestReferenceExerciseSolutionsPresenter extends Tester\TestCase
{
  /** @var ReferenceExerciseSolutionsPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

  /** @var  Nette\DI\Container */
  protected $container;

  /** @var Nette\Security\User */
  private $user;

  /** @var ReferenceExerciseSolutions */
  private $referenceSolutions;

  /** @var Mockery\Mock|\App\Helpers\SubmissionHelper */
  private $submissionHelper;

  /** @var Exercises */
  private $exercises;

  public function __construct()
  {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
    $this->referenceSolutions = $container->getByType(ReferenceExerciseSolutions::class);
    $this->exercises = $container->getByType(Exercises::class);
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);

    $this->presenter = PresenterTestHelper::createPresenter($this->container, ReferenceExerciseSolutionsPresenter::class);
    $this->submissionHelper = Mockery::mock(App\Helpers\SubmissionHelper::class);
    $this->presenter->submissionHelper = $this->submissionHelper;
  }

  protected function tearDown()
  {
    Mockery::close();

    if ($this->user->isLoggedIn()) {
      $this->user->logout(TRUE);
    }
  }

  public function testListSolutionsByExercise()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOneBy(["name" => "Convex hull"]);

    $request = new Nette\Application\Request('V1:Assignments', 'GET', [
      'action' => 'exercise',
      'exerciseId' => $exercise->getId()
    ]);

    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal(1, count($result['payload']));
  }

  public function testEvaluateSingle()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    $solution = current($this->referenceSolutions->findAll());

    // prepare return variables for mocked objects
    $jobId = 'jobId';
    $hwGroups = ["group1", "group2"];

    /** @var Mockery\Mock | JobConfig\SubmissionHeader $mockSubmissionHeader */
    $mockSubmissionHeader = Mockery::mock(JobConfig\SubmissionHeader::class);
    $mockSubmissionHeader->shouldReceive("setId")->withArgs([Mockery::any()])->andReturn($mockSubmissionHeader)->times(2)
      ->shouldReceive("setType")->withArgs([ReferenceSolutionEvaluation::JOB_TYPE])->andReturn($mockSubmissionHeader)->times(2);

    /** @var Mockery\Mock | JobConfig\JobConfig $mockJobConfig */
    $mockJobConfig = Mockery::mock(JobConfig\JobConfig::class);
    $mockJobConfig->shouldReceive("getJobId")->withAnyArgs()->andReturn($jobId)->atLeast(2)
      ->shouldReceive("getSubmissionHeader")->withAnyArgs()->andReturn($mockSubmissionHeader)->times(2)
      ->shouldReceive("getHardwareGroups")->andReturn($hwGroups)->atLeast(2);

    /** @var Mockery\Mock | JobConfig\Storage $mockStorage */
    $mockStorage = Mockery::mock(JobConfig\Storage::class);
    $mockStorage->shouldReceive("getJobConfig")->withAnyArgs()->andReturn($mockJobConfig)->once();
    $this->presenter->jobConfigs = $mockStorage;

    $this->submissionHelper->shouldReceive("initiateEvaluation")->withArgs([
      $mockJobConfig,
      [],
      ["env" => "c-gcc-linux"],
      "group1"
    ])->once()->andReturn("resultUrl1");

    $this->submissionHelper->shouldReceive("initiateEvaluation")->withArgs([
      $mockJobConfig,
      [],
      ["env" => "c-gcc-linux"],
      "group2"
    ])->once()->andReturn("resultUrl2");

    $request = new Nette\Application\Request('V1:ReferenceExerciseSolutions', 'POST',
      ['action' => 'evaluate', 'id' => $solution->getId()]
    );

    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(2, $result['payload']);

    $evaluations = $result['payload']['evaluations'];
    $errors = $result['payload']['errors'];
    Assert::equal(2, count($evaluations));
    Assert::equal(0, count($errors));
  }

}

$testCase = new TestReferenceExerciseSolutionsPresenter();
$testCase->run();
