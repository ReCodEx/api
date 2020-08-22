<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\NotFoundException;
use App\Helpers\BackendSubmitHelper;
use App\Helpers\JobConfig\GeneratorResult;
use App\Helpers\SubmissionHelper;
use App\Model\Entity\Exercise;
use App\Model\Entity\ReferenceExerciseSolution;
use App\Model\Entity\ReferenceSolutionSubmission;
use App\Model\Entity\UploadedFile;
use App\Model\Repository\Exercises;
use App\Model\Repository\ReferenceExerciseSolutions;
use App\Model\Repository\ReferenceSolutionSubmissions;
use App\V1Module\Presenters\ReferenceExerciseSolutionsPresenter;
use Tester\Assert;
use App\Helpers\JobConfig;


/**
 * @testCase
 */
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

    /** @var ReferenceSolutionSubmissions */
    private $referenceSolutionEvaluations;

    /** @var Exercises */
    private $exercises;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);
        $this->user = $container->getByType(\Nette\Security\User::class);
        $this->referenceSolutions = $container->getByType(ReferenceExerciseSolutions::class);
        $this->referenceSolutionEvaluations = $container->getByType(ReferenceSolutionSubmissions::class);
        $this->exercises = $container->getByType(Exercises::class);
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
        $this->presenter = PresenterTestHelper::createPresenter(
            $this->container,
            ReferenceExerciseSolutionsPresenter::class
        );
    }

    protected function tearDown()
    {
        Mockery::close();

        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
    }

    public function testListSolutionsByExercise()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        /** @var Exercise $exercise */
        $exercise = $this->exercises->searchByName("Convex hull")[0];

        $request = new Nette\Application\Request(
            'V1:ReferenceExerciseSolutions', 'GET', [
            'action' => 'solutions',
            'exerciseId' => $exercise->getId()
        ]
        );

        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal(1, count($result['payload']));
    }

    public function testGetSolutionSubmissions()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $solution = current($this->referenceSolutions->findAll());
        $environmentId = $solution->getRuntimeEnvironment()->getId();

        $request = new Nette\Application\Request(
            'V1:ReferenceExerciseSolutions', 'GET', [
            'action' => 'submissions',
            'solutionId' => $solution->getId()
        ]
        );

        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        $payload = $result['payload'];
        Assert::equal(2, count($payload));
        Assert::type(ReferenceSolutionSubmission::class, $payload[0]);
    }

    public function testGetSolutionSubmission()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $evaluation = current($this->referenceSolutionEvaluations->findAll());

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter, 'V1:ReferenceExerciseSolutions', 'GET', [
            'action' => 'submission',
            'submissionId' => $evaluation->getId()
        ]);

        Assert::type(ReferenceSolutionSubmission::class, $payload);
        Assert::equal($evaluation->getId(), $payload->getId());
        Assert::same($evaluation, $payload);
    }

    public function testGetSubmissionScoreConfig()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $evaluation = current($this->referenceSolutionEvaluations->findAll());

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter, 'V1:ReferenceExerciseSolutions', 'GET', [
            'action' => 'evaluationScoreConfig',
            'submissionId' => $evaluation->getId()
        ]);

        Assert::same('weighted', $payload->getCalculator());
        Assert::truthy($payload->getConfig());
    }

    public function testDeleteReferenceSolution()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        /** @var Exercise $exercise */
        $exercise = $this->exercises->searchByName("Convex hull")[0];
        $solution = $exercise->getReferenceSolutions()->first();
        $solutionId = $solution->getId();

        $request = new Nette\Application\Request(
            'V1:ReferenceExerciseSolutions', 'DELETE', [
            'action' => 'deleteReferenceSolution',
            'solutionId' => $solution->getId()
        ]
        );

        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal('OK', $result['payload']);

        Assert::exception(
            function () use ($solutionId) {
                $this->referenceSolutions->findOrThrow($solutionId);
            },
            NotFoundException::class
        );
    }

    public function testDeleteReferenceSolutionSubmission()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $evaluations = $this->referenceSolutionEvaluations->findAll();
        $evaluation = reset($evaluations);
        $evaluationsCount = count($evaluations);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:ReferenceExerciseSolutions',
            'DELETE',
            [
                'action' => 'deleteSubmission',
                'submissionId' => $evaluation->getId()
            ]
        );

        $remainingEvaluations = $this->referenceSolutionEvaluations->findAll();
        Assert::count($evaluationsCount - 1, $remainingEvaluations);
        Assert::notContains(
            $evaluation->getId(),
            array_map(
                function ($eval) {
                    return $eval->getId();
                },
                $remainingEvaluations
            )
        );
    }

    public function testPreSubmit()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $user = current($this->presenter->users->findAll());
        $exercise = current($this->exercises->findAll());
        $exercise->setSolutionFilesLimit(2);
        $exercise->setSolutionSizeLimit(2048);
        $environment = $exercise->getRuntimeEnvironments()->first();
        $ext = current($environment->getExtensionsList());

        // save fake files into db
        $file1 = new UploadedFile("file1." . $ext, new \DateTime(), 1024, $user, "file1." . $ext);
        $file2 = new UploadedFile("file2." . $ext, new \DateTime(), 1024, $user, "file2." . $ext);
        $this->presenter->files->persist($file1);
        $this->presenter->files->persist($file2);
        $this->presenter->files->flush();
        $files = [$file1->getId(), $file2->getId()];

        $request = new Nette\Application\Request(
            'V1:ReferenceExerciseSolutions', 'POST',
            ['action' => 'preSubmit', 'exerciseId' => $exercise->getId()],
            ['files' => $files]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        $payload = $result["payload"];
        Assert::count(4, $payload);
        Assert::true(array_key_exists("environments", $payload));
        Assert::true(array_key_exists("submitVariables", $payload));
        Assert::true($payload['countLimitOK']);
        Assert::true($payload['sizeLimitOK']);

        Assert::equal([$environment->getId()], $payload["environments"]);
        Assert::count(2, $payload["submitVariables"]);
    }

    public function testPreSubmitCountLimitFails()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $user = current($this->presenter->users->findAll());
        $exercise = current($this->exercises->findAll());
        $exercise->setSolutionFilesLimit(1);
        $environment = $exercise->getRuntimeEnvironments()->first();
        $ext = current($environment->getExtensionsList());

        // save fake files into db
        $file1 = new UploadedFile("file1." . $ext, new \DateTime(), 0, $user, "file1." . $ext);
        $file2 = new UploadedFile("file2." . $ext, new \DateTime(), 0, $user, "file2." . $ext);
        $this->presenter->files->persist($file1);
        $this->presenter->files->persist($file2);
        $this->presenter->files->flush();
        $files = [$file1->getId(), $file2->getId()];

        $request = new Nette\Application\Request(
            'V1:ReferenceExerciseSolutions', 'POST',
            ['action' => 'preSubmit', 'exerciseId' => $exercise->getId()],
            ['files' => $files]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        $payload = $result["payload"];
        Assert::count(4, $payload);
        Assert::true(array_key_exists("environments", $payload));
        Assert::true(array_key_exists("submitVariables", $payload));
        Assert::false($payload['countLimitOK']);
        Assert::true($payload['sizeLimitOK']);

        Assert::equal([$environment->getId()], $payload["environments"]);
        Assert::count(2, $payload["submitVariables"]);
    }

    public function testPreSubmitSizeLimitFails()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $user = current($this->presenter->users->findAll());
        $exercise = current($this->exercises->findAll());
        $exercise->setSolutionSizeLimit(2);
        $environment = $exercise->getRuntimeEnvironments()->first();
        $ext = current($environment->getExtensionsList());

        // save fake files into db
        $file1 = new UploadedFile("file1." . $ext, new \DateTime(), 1, $user, "file1." . $ext);
        $file2 = new UploadedFile("file2." . $ext, new \DateTime(), 2, $user, "file2." . $ext);
        $this->presenter->files->persist($file1);
        $this->presenter->files->persist($file2);
        $this->presenter->files->flush();
        $files = [$file1->getId(), $file2->getId()];

        $request = new Nette\Application\Request(
            'V1:ReferenceExerciseSolutions', 'POST',
            ['action' => 'preSubmit', 'exerciseId' => $exercise->getId()],
            ['files' => $files]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        $payload = $result["payload"];
        Assert::count(4, $payload);
        Assert::true(array_key_exists("environments", $payload));
        Assert::true(array_key_exists("submitVariables", $payload));
        Assert::true($payload['countLimitOK']);
        Assert::false($payload['sizeLimitOK']);

        Assert::equal([$environment->getId()], $payload["environments"]);
        Assert::count(2, $payload["submitVariables"]);
    }

    public function testSubmit()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        /** @var Exercise $exercise */
        $exercise = $this->exercises->searchByName("Convex hull")[0];
        $environment = $exercise->getRuntimeEnvironments()->first();
        $user = current($this->presenter->users->findAll());

        // save fake files into db
        $ext = current($environment->getExtensionsList());
        $file1 = new UploadedFile("file1." . $ext, new \DateTime(), 0, $user, "file1." . $ext);
        $file2 = new UploadedFile("file2." . $ext, new \DateTime(), 0, $user, "file2." . $ext);
        $this->presenter->files->persist($file1);
        $this->presenter->files->persist($file2);
        $this->presenter->files->flush();
        $files = [$file1->getId(), $file2->getId()];

        // prepare return variables for mocked objects
        $jobId = 'jobId';

        /** @var Mockery\Mock | JobConfig\SubmissionHeader $mockSubmissionHeader */
        $mockSubmissionHeader = Mockery::mock(JobConfig\SubmissionHeader::class);
        $mockSubmissionHeader->shouldReceive("setId")->withArgs([Mockery::any()])->andReturn(
            $mockSubmissionHeader
        )->times(2)
            ->shouldReceive("setType")->withArgs([ReferenceSolutionSubmission::JOB_TYPE])->andReturn(
                $mockSubmissionHeader
            )->times(2);

        /** @var Mockery\Mock | JobConfig\JobConfig $mockJobConfig */
        $mockJobConfig = Mockery::mock(JobConfig\JobConfig::class);
        $mockJobConfig->shouldReceive("getJobId")->withAnyArgs()->andReturn($jobId)->atLeast(2)
            ->shouldReceive("getSubmissionHeader")->withAnyArgs()->andReturn($mockSubmissionHeader)
            ->shouldReceive("getTasksCount")->andReturn(10);

        /** @var Mockery\Mock | JobConfig\Generator $mockGenerator */
        $mockGenerator = Mockery::mock(JobConfig\Generator::class);
        $mockGenerator->shouldReceive("generateJobConfig")->withAnyArgs()
            ->andReturn(new GeneratorResult("", $mockJobConfig))->once();
        $this->presenter->jobConfigGenerator = $mockGenerator;

        /** @var Mockery\Mock | BackendSubmitHelper $mockBackendSubmitHelper */
        $mockBackendSubmitHelper = Mockery::mock(App\Helpers\BackendSubmitHelper::class);
        $mockBackendSubmitHelper->shouldReceive("initiateEvaluation")->withAnyArgs()->once()->andReturn("resultUrl1");
        $mockBackendSubmitHelper->shouldReceive("initiateEvaluation")->withAnyArgs()->once()->andReturn("resultUrl2");
        $this->presenter->submissionHelper = new SubmissionHelper($mockBackendSubmitHelper);

        $request = new Nette\Application\Request(
            'V1:ReferenceExerciseSolutions', 'POST', [
            'action' => 'submit',
            'exerciseId' => $exercise->getId()
        ], [
                'note' => 'new reference solution',
                'files' => $files,
                'runtimeEnvironmentId' => $environment->getId()
            ]
        );

        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::count(3, $result['payload']);

        $submissions = $result['payload']['submissions'];
        $errors = $result['payload']['errors'];
        Assert::equal(2, count($submissions));
        Assert::equal(0, count($errors));
    }

    public function testResubmit()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $solution = current($this->referenceSolutions->findAll());

        // prepare return variables for mocked objects
        $jobId = 'jobId';

        /** @var Mockery\Mock | JobConfig\SubmissionHeader $mockSubmissionHeader */
        $mockSubmissionHeader = Mockery::mock(JobConfig\SubmissionHeader::class);
        $mockSubmissionHeader->shouldReceive("setId")->withArgs([Mockery::any()])->andReturn(
            $mockSubmissionHeader
        )->times(2)
            ->shouldReceive("setType")->withArgs([ReferenceSolutionSubmission::JOB_TYPE])->andReturn(
                $mockSubmissionHeader
            )->times(2);

        /** @var Mockery\Mock | JobConfig\JobConfig $mockJobConfig */
        $mockJobConfig = Mockery::mock(JobConfig\JobConfig::class);
        $mockJobConfig->shouldReceive("getJobId")->withAnyArgs()->andReturn($jobId)->atLeast(2)
            ->shouldReceive("getSubmissionHeader")->withAnyArgs()->andReturn($mockSubmissionHeader)
            ->shouldReceive("getTasksCount")->andReturn(10);

        /** @var Mockery\Mock | JobConfig\Generator $mockGenerator */
        $mockGenerator = Mockery::mock(JobConfig\Generator::class);
        $mockGenerator->shouldReceive("generateJobConfig")->withAnyArgs()
            ->andReturn(new GeneratorResult("", $mockJobConfig))->once();
        $this->presenter->jobConfigGenerator = $mockGenerator;

        /** @var Mockery\Mock | BackendSubmitHelper $mockBackendSubmitHelper */
        $mockBackendSubmitHelper = Mockery::mock(App\Helpers\BackendSubmitHelper::class);
        $mockBackendSubmitHelper->shouldReceive("initiateEvaluation")->withArgs(
            [
                $mockJobConfig,
                [],
                ["env" => "c-gcc-linux"],
                "group1"
            ]
        )->once()->andReturn("resultUrl1");
        $mockBackendSubmitHelper->shouldReceive("initiateEvaluation")->withArgs(
            [
                $mockJobConfig,
                [],
                ["env" => "c-gcc-linux"],
                "group2"
            ]
        )->once()->andReturn("resultUrl2");
        $this->presenter->submissionHelper = new SubmissionHelper($mockBackendSubmitHelper);

        $request = new Nette\Application\Request(
            'V1:ReferenceExerciseSolutions', 'POST',
            ['action' => 'resubmit', 'id' => $solution->getId()]
        );

        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::count(3, $result['payload']);

        $submissions = $result['payload']['submissions'];
        $errors = $result['payload']['errors'];
        Assert::equal(2, count($submissions));
        Assert::equal(0, count($errors));
    }

    public function testResubmitAll()
    {
        // @todo
        Assert::true(true);
    }

    public function testDownloadSolutionArchive()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $solution = current($this->presenter->referenceSolutions->findAll());

        $request = new Nette\Application\Request(
            'V1:ReferenceExerciseSolutions',
            'GET',
            ['action' => 'downloadSolutionArchive', 'solutionId' => $solution->id]
        );
        $response = $this->presenter->run($request);
        Assert::same(App\Responses\ZipFilesResponse::class, get_class($response));

        // Check invariants
        Assert::equal("reference-solution-" . $solution->getId() . '.zip', $response->getName());
    }

    public function testDownloadResultArchive()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        /** @var Evaluation $evaluation */
        $evaluation = current($this->presenter->referenceSubmissions->findAll());

        // mock everything you can
        $mockGuzzleStream = Mockery::mock(Psr\Http\Message\StreamInterface::class);
        $mockGuzzleStream->shouldReceive("getSize")->andReturn(0);
        $mockGuzzleStream->shouldReceive("eof")->andReturn(true);

        $mockProxy = Mockery::mock(App\Helpers\FileServerProxy::class);
        $mockProxy->shouldReceive("getFileserverFileStream")->withAnyArgs()->andReturn($mockGuzzleStream);
        $this->presenter->fileServerProxy = $mockProxy;

        $request = new Nette\Application\Request(
            'V1:ReferenceExerciseSolutions', 'GET', [
            'action' => 'downloadResultArchive',
            'submissionId' => $evaluation->getId()
        ]
        );

        $response = $this->presenter->run($request);
        Assert::same(App\Responses\GuzzleResponse::class, get_class($response));

        // Check invariants
        Assert::equal("results-" . $evaluation->getId() . '.zip', $response->getName());
    }

}

$testCase = new TestReferenceExerciseSolutionsPresenter();
$testCase->run();
