<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\NotFoundException;
use App\Exceptions\ForbiddenRequestException;
use App\Helpers\BackendSubmitHelper;
use App\Helpers\SubmissionHelper;
use App\Helpers\FileStorageManager;
use App\Helpers\FileStorage\LocalImmutableFile;
use App\Helpers\TmpFilesHelper;
use App\Helpers\FileStorage\LocalFileStorage;
use App\Helpers\FileStorage\LocalHashFileStorage;
use App\Model\Entity\Exercise;
use App\Model\Entity\ReferenceExerciseSolution;
use App\Model\Entity\ReferenceSolutionSubmission;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\SolutionFile;
use App\Model\Repository\Exercises;
use App\Model\Repository\ReferenceExerciseSolutions;
use App\Model\Repository\ReferenceSolutionSubmissions;
use App\Model\Repository\Users;
use App\V1Module\Presenters\ReferenceExerciseSolutionsPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Tester\Assert;
use App\Helpers\JobConfig;


/**
 * @testCase
 */
class TestReferenceExerciseSolutionsPresenter extends Tester\TestCase
{
    /** @var ReferenceExerciseSolutionsPresenter */
    protected $presenter;

    /** @var EntityManagerInterface */
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

        // patch container, since we cannot create actual file storage manarer
        $fsName = current($this->container->findByType(FileStorageManager::class));
        $this->container->removeService($fsName);
        $this->container->addService($fsName, new FileStorageManager(
            Mockery::mock(LocalFileStorage::class),
            Mockery::mock(LocalHashFileStorage::class),
            Mockery::mock(TmpFilesHelper::class),
            ""
        ));
    }

    private function createSubmissionHelper($mockBackendSubmitHelper, $mockGenerator = null, $mockFileStorage = null)
    {
        return new SubmissionHelper(
            $this->container->getByType(App\Helpers\SubmissionConfigHelper::class),
            $mockBackendSubmitHelper,
            $this->container->getByType(App\Model\Repository\AssignmentSolutions::class),
            $this->container->getByType(App\Model\Repository\AssignmentSolutionSubmissions::class),
            $this->container->getByType(App\Model\Repository\ReferenceExerciseSolutions::class),
            $this->container->getByType(App\Model\Repository\ReferenceSolutionSubmissions::class),
            $this->container->getByType(App\Model\Repository\SubmissionFailures::class),
            $this->container->getByType(App\Helpers\FailureHelper::class),
            $mockGenerator ?? $this->container->getByType(App\Helpers\JobConfig\Generator::class),
            $mockFileStorage ?? $this->container->getByType(App\Helpers\FileStorageManager::class),
            $this->container->getByType(App\Model\Repository\UploadedFiles::class),
        );
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
        PresenterTestHelper::login($this->container, PresenterTestHelper::GROUP_SUPERVISOR_LOGIN);
        /** @var Exercise $exercise */
        $exercise = $this->exercises->searchByName("Convex hull")[0];

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:ReferenceExerciseSolutions',
            'GET',
            [ 'action' => 'solutions', 'exerciseId' => $exercise->getId() ]
        );

        Assert::count(1, $payload);
    }

    public function testListSolutionsByExercise2()
    {
        // another supervisor can see his private ref. solution
        PresenterTestHelper::login($this->container, PresenterTestHelper::ANOTHER_SUPERVISOR_LOGIN);
        /** @var Exercise $exercise */
        $exercise = $this->exercises->searchByName("Convex hull")[0];
        $exercise->addAdmin($this->container->getByType(Users::class)->getByEmail(PresenterTestHelper::ANOTHER_SUPERVISOR_LOGIN));

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:ReferenceExerciseSolutions',
            'GET',
            [ 'action' => 'solutions', 'exerciseId' => $exercise->getId() ]
        );

        Assert::count(2, $payload);
    }

    public function testGetSolutionSubmissions()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $solution = current($this->referenceSolutions->findAll());
        $environmentId = $solution->getSolution()->getRuntimeEnvironment()->getId();

        $request = new Nette\Application\Request(
            'V1:ReferenceExerciseSolutions',
            'GET',
            [
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
            $this->presenter,
            'V1:ReferenceExerciseSolutions',
            'GET',
            [
            'action' => 'submission',
            'submissionId' => $evaluation->getId()
            ]
        );

        Assert::type(ReferenceSolutionSubmission::class, $payload);
        Assert::equal($evaluation->getId(), $payload->getId());
        Assert::same($evaluation, $payload);
    }

    public function testGetSubmissionScoreConfig()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $evaluation = current($this->referenceSolutionEvaluations->findAll());

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:ReferenceExerciseSolutions',
            'GET',
            [
            'action' => 'evaluationScoreConfig',
            'submissionId' => $evaluation->getId()
            ]
        );

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

        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("deleteSolutionArchive")->with($solution->getSolution())->once();
        foreach ($solution->getSubmissions()->getValues() as $submission) {
            $mockFileStorage->shouldReceive("deleteResultsArchive")->with($submission)->once();
            $mockFileStorage->shouldReceive("deleteJobConfig")->with($submission)->once();
        }
        $this->presenter->fileStorage = $mockFileStorage;

        $request = new Nette\Application\Request(
            'V1:ReferenceExerciseSolutions',
            'DELETE',
            [
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

        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("deleteResultsArchive")->with($evaluation)->once();
        $mockFileStorage->shouldReceive("deleteJobConfig")->with($evaluation)->once();
        $this->presenter->fileStorage = $mockFileStorage;

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
        $file1 = new UploadedFile("file1.$ext", new \DateTime(), 1024, $user);
        $file2 = new UploadedFile("file2.$ext", new \DateTime(), 1024, $user);
        $this->presenter->files->persist($file1);
        $this->presenter->files->persist($file2);
        $this->presenter->files->flush();
        $files = [$file1->getId(), $file2->getId()];

        $request = new Nette\Application\Request(
            'V1:ReferenceExerciseSolutions',
            'POST',
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
        $file1 = new UploadedFile("file1.$ext", new \DateTime(), 0, $user);
        $file2 = new UploadedFile("file2.$ext", new \DateTime(), 0, $user);
        $this->presenter->files->persist($file1);
        $this->presenter->files->persist($file2);
        $this->presenter->files->flush();
        $files = [$file1->getId(), $file2->getId()];

        $request = new Nette\Application\Request(
            'V1:ReferenceExerciseSolutions',
            'POST',
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
        $file1 = new UploadedFile("file1.$ext", new \DateTime(), 1, $user);
        $file2 = new UploadedFile("file2.$ext", new \DateTime(), 2, $user);
        $this->presenter->files->persist($file1);
        $this->presenter->files->persist($file2);
        $this->presenter->files->flush();
        $files = [$file1->getId(), $file2->getId()];

        $request = new Nette\Application\Request(
            'V1:ReferenceExerciseSolutions',
            'POST',
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
        $file1 = new UploadedFile("file1.$ext", new \DateTime(), 0, $user);
        $file2 = new UploadedFile("file2.$ext", new \DateTime(), 0, $user);
        $this->presenter->files->persist($file1);
        $this->presenter->files->persist($file2);
        $this->presenter->files->flush();
        $files = [$file1->getId(), $file2->getId()];

        // prepare return variables for mocked objects
        $jobId = 'jobId';

        /** @var Mockery\Mock | JobConfig\SubmissionHeader $mockSubmissionHeader */
        $mockSubmissionHeader = Mockery::mock(JobConfig\SubmissionHeader::class);

        /** @var Mockery\Mock | JobConfig\JobConfig $mockJobConfig */
        $mockJobConfig = Mockery::mock(JobConfig\JobConfig::class);
        $mockJobConfig->shouldReceive("getJobId")->withAnyArgs()->andReturn($jobId)->atLeast(2)
            ->shouldReceive("getSubmissionHeader")->withAnyArgs()->andReturn($mockSubmissionHeader)
            ->shouldReceive("getTasksCount")->andReturn(10);

        /** @var Mockery\Mock | JobConfig\Generator $mockGenerator */
        $mockGenerator = Mockery::mock(JobConfig\Generator::class);
        $mockGenerator->shouldReceive("generateJobConfig")->withAnyArgs()
            ->andReturn($mockJobConfig)->atLeast(1);
        $this->presenter->jobConfigGenerator = $mockGenerator;

        /** @var Mockery\Mock | BackendSubmitHelper $mockBackendSubmitHelper */
        $mockBackendSubmitHelper = Mockery::mock(App\Helpers\BackendSubmitHelper::class);
        $mockBackendSubmitHelper->shouldReceive("initiateEvaluation")->withAnyArgs()->once()->andReturn("resultUrl1");
        $mockBackendSubmitHelper->shouldReceive("initiateEvaluation")->withAnyArgs()->once()->andReturn("resultUrl2");

        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("storeUploadedSolutionFile")->withArgs([Mockery::any(), $file1])->once()
            ->shouldReceive("storeUploadedSolutionFile")->withArgs([Mockery::any(), $file2])->once();
        $this->presenter->fileStorage = $mockFileStorage;
        $this->presenter->submissionHelper = $this->createSubmissionHelper($mockBackendSubmitHelper, $mockGenerator, $mockFileStorage);

        $request = new Nette\Application\Request(
            'V1:ReferenceExerciseSolutions',
            'POST',
            [
            'action' => 'submit',
            'exerciseId' => $exercise->getId()
            ],
            [
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

    public function testSubmitToArchivedExercise()
    {
        PresenterTestHelper::login($this->container, PresenterTestHelper::GROUP_SUPERVISOR_LOGIN);

        /** @var Exercise $exercise */
        $exercise = array_values(array_filter($this->presenter->exercises->findAll(), function ($e) {
            return $e->isArchived();
        }))[0];
        $environment = $exercise->getRuntimeEnvironments()->first();
        $user = current($this->presenter->users->findAll());

        // save fake files into db
        $ext = current($environment->getExtensionsList());
        $file1 = new UploadedFile("file1.$ext", new \DateTime(), 0, $user);
        $this->presenter->files->persist($file1);
        $this->presenter->files->flush();
        $files = [$file1->getId()];

        // prepare return variables for mocked objects
        $jobId = 'jobId';

        $request = new Nette\Application\Request(
            'V1:ReferenceExerciseSolutions',
            'POST',
            [
            'action' => 'submit',
            'exerciseId' => $exercise->getId()
            ],
            [
                'note' => 'new reference solution',
                'files' => $files,
                'runtimeEnvironmentId' => $environment->getId()
            ]
        );
        Assert::exception(
            function () use ($request) {
                $this->presenter->run($request);
            },
            App\Exceptions\ForbiddenRequestException::class
        );
    }

    public function testResubmit()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $solution = current($this->referenceSolutions->findAll());

        // prepare return variables for mocked objects
        $jobId = 'jobId';

        /** @var Mockery\Mock | JobConfig\SubmissionHeader $mockSubmissionHeader */
        $mockSubmissionHeader = Mockery::mock(JobConfig\SubmissionHeader::class);

        /** @var Mockery\Mock | JobConfig\JobConfig $mockJobConfig */
        $mockJobConfig = Mockery::mock(JobConfig\JobConfig::class);
        $mockJobConfig->shouldReceive("getJobId")->withAnyArgs()->andReturn($jobId)->atLeast(2)
            ->shouldReceive("getSubmissionHeader")->withAnyArgs()->andReturn($mockSubmissionHeader)
            ->shouldReceive("getTasksCount")->andReturn(10);

        /** @var Mockery\Mock | JobConfig\Generator $mockGenerator */
        $mockGenerator = Mockery::mock(JobConfig\Generator::class);
        $mockGenerator->shouldReceive("generateJobConfig")->withAnyArgs()
            ->andReturn($mockJobConfig)->times(2);
        $this->presenter->jobConfigGenerator = $mockGenerator;

        /** @var Mockery\Mock | BackendSubmitHelper $mockBackendSubmitHelper */
        $mockBackendSubmitHelper = Mockery::mock(App\Helpers\BackendSubmitHelper::class);
        $mockBackendSubmitHelper->shouldReceive("initiateEvaluation")->withArgs(
            [
                $mockJobConfig,
                ["env" => "c-gcc-linux"],
                "group1"
            ]
        )->andReturn(true)->once();
        $mockBackendSubmitHelper->shouldReceive("initiateEvaluation")->withArgs(
            [
                $mockJobConfig,
                ["env" => "c-gcc-linux"],
                "group2"
            ]
        )->andReturn(true)->once();
        $this->presenter->submissionHelper = $this->createSubmissionHelper($mockBackendSubmitHelper, $mockGenerator);

        $request = new Nette\Application\Request(
            'V1:ReferenceExerciseSolutions',
            'POST',
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

        $mockFile = Mockery::mock(LocalImmutableFile::class);
        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("getSolutionFile")->with($solution->getSolution())->andReturn($mockFile)->once();
        $this->presenter->fileStorage = $mockFileStorage;

        $request = new Nette\Application\Request(
            'V1:ReferenceExerciseSolutions',
            'GET',
            ['action' => 'downloadSolutionArchive', 'solutionId' => $solution->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(App\Responses\StorageFileResponse::class, $response);

        // Check invariants
        Assert::equal("reference-solution-" . $solution->getId() . '.zip', $response->getName());
    }

    public function testDownloadResultArchive()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $submission = current($this->presenter->referenceSubmissions->findAll());

        $mockFile = Mockery::mock(LocalImmutableFile::class);
        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("getResultsArchive")->with($submission)->andReturn($mockFile)->once();
        $this->presenter->fileStorage = $mockFileStorage;

        $request = new Nette\Application\Request(
            'V1:ReferenceExerciseSolutions',
            'GET',
            [
            'action' => 'downloadResultArchive',
            'submissionId' => $submission->getId()
            ]
        );

        $response = $this->presenter->run($request);
        Assert::type(App\Responses\StorageFileResponse::class, $response);
        Assert::equal("results-" . $submission->getId() . '.zip', $response->getName());
    }

    public function testGetSolutionFiles()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $solution = current($this->presenter->referenceSolutions->findAll());
        Assert::truthy($solution);
        $file = new SolutionFile("source.py", new DateTime(), 123, $solution->getSolution()->getAuthor(), $solution->getSolution());
        $this->presenter->files->persist($file);
        Assert::false($solution->getSolution()->getFiles()->isEmpty());

        $result = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:ReferenceExerciseSolutions',
            'GET',
            ['action' => 'files', 'id' => $solution->getId()]
        );

        Assert::same(json_encode($solution->getSolution()->getFiles()->toArray()), json_encode($result));
    }

    public function testSetVisibilityPrivate()
    {
        PresenterTestHelper::login($this->container, PresenterTestHelper::GROUP_SUPERVISOR_LOGIN);
        $solution = current(array_filter($this->referenceSolutions->findAll(), function ($rs) {
            return $rs->getVisibility() >= ReferenceExerciseSolution::VISIBILITY_PUBLIC;
        }));

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:ReferenceExerciseSolutions',
            'POST',
            [ 'action' => 'setVisibility', 'solutionId' => $solution->getId() ],
            [ 'visibility' => ReferenceExerciseSolution::VISIBILITY_PRIVATE ]
        );

        $this->referenceSolutions->refresh($solution);
        Assert::equal(ReferenceExerciseSolution::VISIBILITY_PRIVATE, $payload['visibility']);
        Assert::equal(ReferenceExerciseSolution::VISIBILITY_PRIVATE, $solution->getVisibility());
    }

    public function testSetVisibilityPromoted()
    {
        PresenterTestHelper::login($this->container, PresenterTestHelper::GROUP_SUPERVISOR_LOGIN);
        $solution = current(array_filter($this->referenceSolutions->findAll(), function ($rs) {
            return $rs->getVisibility() >= ReferenceExerciseSolution::VISIBILITY_PUBLIC;
        }));

        // not-owner of the exercise cannot promote
        Assert::exception(
            function () use ($solution) {
                $payload = PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:ReferenceExerciseSolutions',
                    'POST',
                    [ 'action' => 'setVisibility', 'solutionId' => $solution->getId() ],
                    [ 'visibility' => ReferenceExerciseSolution::VISIBILITY_PROMOTED ]
                );
            },
            ForbiddenRequestException::class
        );

        PresenterTestHelper::loginDefaultAdmin($this->container);
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:ReferenceExerciseSolutions',
            'POST',
            [ 'action' => 'setVisibility', 'solutionId' => $solution->getId() ],
            [ 'visibility' => ReferenceExerciseSolution::VISIBILITY_PROMOTED ]
        );

        $this->referenceSolutions->refresh($solution);
        Assert::equal(ReferenceExerciseSolution::VISIBILITY_PROMOTED, $payload['visibility']);
        Assert::equal(ReferenceExerciseSolution::VISIBILITY_PROMOTED, $solution->getVisibility());
    }
}

$testCase = new TestReferenceExerciseSolutionsPresenter();
$testCase->run();
