<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Helpers\BackendSubmitHelper;
use App\Helpers\SubmissionHelper;
use App\Model\Entity\Exercise;
use App\Model\Entity\ReferenceExerciseSolution;
use App\Model\Entity\ReferenceSolutionEvaluation;
use App\Model\Entity\UploadedFile;
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

    $request = new Nette\Application\Request('V1:ReferenceExerciseSolutions', 'GET', [
      'action' => 'exercise',
      'exerciseId' => $exercise->getId()
    ]);

    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal(1, count($result['payload']));
  }

  public function testGetSolutionEvaluations()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    $solution = current($this->referenceSolutions->findAll());
    $environmentId = $solution->getRuntimeEnvironment()->getId();

    $request = new Nette\Application\Request('V1:ReferenceExerciseSolutions', 'GET', [
      'action' => 'evaluations',
      'solutionId' => $solution->getId()
    ]);

    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    $payload = $result['payload'];
    Assert::equal(1, count($payload));
    Assert::count(1, $payload[$environmentId]);
    Assert::type(ReferenceSolutionEvaluation::class, $payload[$environmentId][0]);
  }

  public function testCreateReferenceSolution()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOneBy(["name" => "Convex hull"]);
    $environment = $exercise->getRuntimeEnvironments()->first();
    $user = current($this->presenter->users->findAll());

    // save fake files into db
    $ext = current($environment->getExtensionsList());
    $file1 = new UploadedFile("file1." . $ext, new \DateTime, 0, $user, "file1." . $ext);
    $file2 = new UploadedFile("file2." . $ext, new \DateTime, 0, $user, "file2." . $ext);
    $this->presenter->files->persist($file1);
    $this->presenter->files->persist($file2);
    $this->presenter->files->flush();
    $files = [ $file1->getId(), $file2->getId() ];

    $request = new Nette\Application\Request('V1:ReferenceExerciseSolutions', 'POST', [
      'action' => 'createReferenceSolution',
      'exerciseId' => $exercise->getId()
    ], [
      'note' => 'new reference solution',
      'files' => $files,
      'runtimeEnvironmentId' => $environment->getId()
    ]);

    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    /** @var ReferenceExerciseSolution $payload */
    $payload = $result['payload'];
    Assert::type(ReferenceExerciseSolution::class, $payload);
    Assert::equal('new reference solution', $payload->getDescription());
    Assert::equal($exercise->getId(), $payload->getExercise()->getId());
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
      ->shouldReceive("getSubmissionHeader")->withAnyArgs()->andReturn($mockSubmissionHeader);

    /** @var Mockery\Mock | JobConfig\Generator $mockGenerator */
    $mockGenerator = Mockery::mock(JobConfig\Generator::class);
    $mockGenerator->shouldReceive("generateJobConfig")->withAnyArgs()->andReturn(array("", $mockJobConfig))->once();
    $this->presenter->jobConfigGenerator = $mockGenerator;

    /** @var Mockery\Mock | BackendSubmitHelper $mockBackendSubmitHelper */
    $mockBackendSubmitHelper = Mockery::mock(App\Helpers\BackendSubmitHelper::class);
    $mockBackendSubmitHelper->shouldReceive("initiateEvaluation")->withArgs([
      $mockJobConfig,
      [],
      ["env" => "c-gcc-linux"],
      "group1"
    ])->once()->andReturn("resultUrl1");
    $mockBackendSubmitHelper->shouldReceive("initiateEvaluation")->withArgs([
      $mockJobConfig,
      [],
      ["env" => "c-gcc-linux"],
      "group2"
    ])->once()->andReturn("resultUrl2");
    $this->presenter->submissionHelper = new SubmissionHelper($mockBackendSubmitHelper);

    $request = new Nette\Application\Request('V1:ReferenceExerciseSolutions', 'POST',
      ['action' => 'evaluate', 'id' => $solution->getId()]
    );

    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(3, $result['payload']);

    $evaluations = $result['payload']['evaluations'];
    $errors = $result['payload']['errors'];
    Assert::equal(2, count($evaluations));
    Assert::equal(0, count($errors));
  }

  public function testEvaluateMultiple()
  {
    // @todo
  }

  public function testDownloadResultArchive()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    /** @var Evaluation $evaluation */
    $evaluation = current($this->presenter->referenceEvaluations->findAll());

    // mock everything you can
    $mockGuzzleStream = Mockery::mock(Psr\Http\Message\StreamInterface::class);
    $mockGuzzleStream->shouldReceive("getSize")->andReturn(0);
    $mockGuzzleStream->shouldReceive("eof")->andReturn(true);

    $mockProxy = Mockery::mock(App\Helpers\FileServerProxy::class);
    $mockProxy->shouldReceive("getResultArchiveStream")->withAnyArgs()->andReturn($mockGuzzleStream);
    $this->presenter->fileServerProxy = $mockProxy;

    $request = new Nette\Application\Request('V1:ReferenceExerciseSolutions', 'GET', [
      'action' => 'downloadResultArchive',
      'evaluationId' => $evaluation->getId()
    ]);

    $response = $this->presenter->run($request);
    Assert::same(App\Responses\GuzzleResponse::class, get_class($response));

    // Check invariants
    Assert::equal($evaluation->getId() . '.zip', $response->getName());
  }

}

$testCase = new TestReferenceExerciseSolutionsPresenter();
$testCase->run();
