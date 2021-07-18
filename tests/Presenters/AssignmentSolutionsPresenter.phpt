<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\NotFoundException;
use App\Helpers\Notifications\PointsChangedEmailsSender;
use App\Model\Entity\Assignment;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\User;
use App\V1Module\Presenters\AssignmentSolutionsPresenter;
use App\Helpers\FileStorageManager;
use App\Helpers\FileStorage\LocalImmutableFile;
use App\Helpers\TmpFilesHelper;
use App\Helpers\FileStorage\LocalFileStorage;
use App\Helpers\FileStorage\LocalHashFileStorage;
use Doctrine\ORM\EntityManagerInterface;
use Tester\Assert;


/**
 * @testCase
 */
class TestAssignmentSolutionsPresenter extends Tester\TestCase
{
    /** @var AssignmentSolutionsPresenter */
    protected $presenter;

    /** @var EntityManagerInterface */
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

        $request = new Nette\Application\Request(
            'V1:AssignmentSolutions',
            'GET',
            ['action' => 'solution', 'id' => $solution->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));

        // Check invariants
        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::same($solution->getId(), $result['payload']['id']);
    }

    public function testUpdateSolution()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $solution = current($this->presenter->assignmentSolutions->findAll());

        $result = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolutions',
            'POST',
            ['action' => 'updateSolution', 'id' => $solution->getId()],
            ['note' => 'changed note of the solution']
        );

        $solution = $this->presenter->assignmentSolutions->get($solution->getId());
        Assert::equal('changed note of the solution', $result['note']);
        Assert::equal('changed note of the solution', $solution->getNote());
    }

    public function testGetSubmissions()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $solution = current($this->presenter->assignmentSolutions->findAll());

        $request = new Nette\Application\Request(
            'V1:AssignmentSolutions',
            'GET',
            ['action' => 'submissions', 'id' => $solution->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));

        // Check invariants
        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        $payload = $result['payload'];
        Assert::count($solution->getSubmissions()->count(), $payload);
    }

    public function testGetSubmission()
    {
        PresenterTestHelper::login($this->container, "submitUser1@example.com", "password");
        $submissionsWithEval = array_filter(
            $this->presenter->assignmentSolutionSubmissions->findAll(),
            function($submission) { return $submission->getEvaluation() !== null; }
        );
        $submission = array_pop($submissionsWithEval);
        $evaluation = $submission->getEvaluation();
        Assert::truthy($evaluation);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter, 'V1:AssignmentSolutions', 'GET',
            ['action' => 'submission', 'submissionId' => $submission->getId()]
        );
        Assert::same($submission->getId(), $payload['id']);
    }

    public function testGetEvaluationScoreConfig()
    {
        PresenterTestHelper::login($this->container, "submitUser1@example.com", "password");
        $submissionsWithEval = array_filter(
            $this->presenter->assignmentSolutionSubmissions->findAll(),
            function($submission) { return $submission->getEvaluation() !== null; }
        );
        $submission = array_pop($submissionsWithEval);
        $evaluation = $submission->getEvaluation();
        Assert::truthy($evaluation);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter, 'V1:AssignmentSolutions', 'GET',
            ['action' => 'evaluationScoreConfig', 'submissionId' => $submission->getId() ]
        );
        Assert::same('weighted', $payload->getCalculator());
        Assert::truthy($payload->getConfig());
    }

    public function testDeleteSubmission()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $allSubmissions = $this->presenter->assignmentSolutionSubmissions->findAll();
        $submission = reset($allSubmissions);
        $submissionsCount = count($allSubmissions);

        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("deleteResultsArchive")->withArgs([$submission])->once();
        $mockFileStorage->shouldReceive("deleteJobConfig")->withArgs([$submission])->once();
        $this->presenter->fileStorage = $mockFileStorage;

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:AssignmentSolutions',
            'DELETE',
            [
                'action' => 'deleteSubmission',
                'submissionId' => $submission->getId()
            ]
        );

        $remainingSubmissions = $this->presenter->assignmentSolutionSubmissions->findAll();
        Assert::count($submissionsCount - 1, $remainingSubmissions);
        Assert::notContains(
            $submission->getId(),
            array_map(
                function ($eval) {
                    return $eval->getId();
                },
                $remainingSubmissions
            )
        );
    }

    public function testSetBonusPoints()
    {
        $token = PresenterTestHelper::login($this->container, "admin@admin.com", "admin");
        $solution = current($this->presenter->assignmentSolutions->findAll());

        /** @var Mockery\Mock | PointsChangedEmailsSender $mockPointsEmailsSender */
        $mockPointsEmailsSender = Mockery::mock(PointsChangedEmailsSender::class);
        $mockPointsEmailsSender->shouldReceive("solutionPointsUpdated")->with($solution)->andReturn(true)->once();
        $this->presenter->pointsChangedEmailsSender = $mockPointsEmailsSender;

        $request = new Nette\Application\Request(
            'V1:AssignmentSolutions',
            'POST',
            ['action' => 'setBonusPoints', 'id' => $solution->getId()],
            ['bonusPoints' => 4, 'overriddenPoints' => 857]
        );
        $response = $this->presenter->run($request);
        Assert::same(Nette\Application\Responses\JsonResponse::class, get_class($response));

        // Check invariants
        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal("OK", $result['payload']);

        $solution = $this->presenter->assignmentSolutions->get($solution->getId());
        Assert::equal(4, $solution->getBonusPoints());
        Assert::equal(857, $solution->getOverriddenPoints());
    }

    public function testSetFlagAcceptedTrue()
    {
        $allSolutions = $this->presenter->assignmentSolutions->findAll();
        /** @var AssignmentSolution $solution */
        $solution = array_pop($allSolutions);
        $assignment = $solution->getAssignment();

        $user = $this->getSupervisorWhoIsNotAuthorOrSuperadmin($assignment, $solution);
        Assert::notSame(null, $user);

        PresenterTestHelper::login($this->container, $user->getEmail());

        $request = new Nette\Application\Request(
            'V1:AssignmentSolutions',
            'POST',
            ['action' => 'setFlag', 'id' => $solution->getId(), 'flag' => 'accepted'],
            ['value' => true]
        );
        $response = $this->presenter->run($request);
        Assert::same(Nette\Application\Responses\ForwardResponse::class, get_class($response));

        // Check invariants
        $solution = $this->presenter->assignmentSolutions->get($solution->getId());
        Assert::true($solution->isAccepted());
    }

    public function testSetFlagAcceptedFalse()
    {
        $allSolutions = $this->presenter->assignmentSolutions->findAll();
        /** @var AssignmentSolution $solution */
        $solution = array_pop($allSolutions);
        $assignment = $solution->getAssignment();

        // set accepted flag
        $solution->setAccepted(true);
        $this->presenter->assignmentSolutions->flush();
        Assert::true($solution->isAccepted());

        $user = $this->getSupervisorWhoIsNotAuthorOrSuperadmin($assignment, $solution);
        Assert::notSame(null, $user);

        PresenterTestHelper::login($this->container, $user->getEmail());

        $request = new Nette\Application\Request(
            'V1:AssignmentSolutions',
            'POST',
            ['action' => 'setFlag', 'id' => $solution->getId(), 'flag' => 'accepted'],
            ['value' => false]
        );
        $response = $this->presenter->run($request);
        Assert::same(Nette\Application\Responses\ForwardResponse::class, get_class($response));

        // Check invariants
        $solution = $this->presenter->assignmentSolutions->get($solution->getId());
        Assert::false($solution->isAccepted());
    }

    public function testSetFlagReviewedTrue()
    {
        $allSolutions = $this->presenter->assignmentSolutions->findAll();
        /** @var AssignmentSolution $solution */
        $solution = array_pop($allSolutions);
        $assignment = $solution->getAssignment();

        $user = $this->getSupervisorWhoIsNotAuthorOrSuperadmin($assignment, $solution);
        Assert::notSame(null, $user);

        PresenterTestHelper::login($this->container, $user->getEmail());

        $request = new Nette\Application\Request(
            'V1:AssignmentSolutions',
            'POST',
            ['action' => 'setFlag', 'id' => $solution->getId(), 'flag' => 'reviewed'],
            ['value' => true]
        );
        $response = $this->presenter->run($request);
        Assert::same(Nette\Application\Responses\ForwardResponse::class, get_class($response));

        // Check invariants
        $solution = $this->presenter->assignmentSolutions->get($solution->getId());
        Assert::true($solution->isReviewed());
    }

    public function testSetFlagReviewedFalse()
    {
        $allSolutions = $this->presenter->assignmentSolutions->findAll();
        /** @var AssignmentSolution $solution */
        $solution = array_pop($allSolutions);
        $assignment = $solution->getAssignment();

        // set accepted flag
        $solution->setReviewed(true);
        $this->presenter->assignmentSolutions->flush();
        Assert::true($solution->isReviewed());

        $user = $this->getSupervisorWhoIsNotAuthorOrSuperadmin($assignment, $solution);
        Assert::notSame(null, $user);

        PresenterTestHelper::login($this->container, $user->getEmail());

        $request = new Nette\Application\Request(
            'V1:AssignmentSolutions',
            'POST',
            ['action' => 'setFlag', 'id' => $solution->getId(), 'flag' => 'reviewed'],
            ['value' => false]
        );
        $response = $this->presenter->run($request);
        Assert::same(Nette\Application\Responses\ForwardResponse::class, get_class($response));

        // Check invariants
        $solution = $this->presenter->assignmentSolutions->get($solution->getId());
        Assert::false($solution->isReviewed());
    }

    public function testSetAccepted()
    {
        $allSolutions = $this->presenter->assignmentSolutions->findAll();
        /** @var AssignmentSolution $solution */
        $solution = array_pop($allSolutions);
        $assignment = $solution->getAssignment();

        $user = $this->getSupervisorWhoIsNotAuthorOrSuperadmin($assignment, $solution);
        Assert::notSame(null, $user);

        PresenterTestHelper::login($this->container, $user->getEmail());

        $request = new Nette\Application\Request(
            'V1:AssignmentSolutions',
            'POST',
            ['action' => 'setAccepted', 'id' => $solution->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::same(Nette\Application\Responses\ForwardResponse::class, get_class($response));

        // Check invariants
        $solution = $this->presenter->assignmentSolutions->get($solution->getId());
        Assert::true($solution->isAccepted());
    }

    public function testUnsetAccepted()
    {
        $allSolutions = $this->presenter->assignmentSolutions->findAll();
        /** @var AssignmentSolution $solution */
        $solution = array_pop($allSolutions);
        $assignment = $solution->getAssignment();

        // set accepted flag
        $solution->setAccepted(true);
        $this->presenter->assignmentSolutions->flush();
        Assert::true($solution->isAccepted());

        $user = $this->getSupervisorWhoIsNotAuthorOrSuperadmin($assignment, $solution);
        Assert::notSame(null, $user);

        PresenterTestHelper::login($this->container, $user->getEmail());

        $request = new Nette\Application\Request(
            'V1:AssignmentSolutions',
            'DELETE',
            ['action' => 'unsetAccepted', 'id' => $solution->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::same(Nette\Application\Responses\ForwardResponse::class, get_class($response));

        // Check invariants
        $solution = $this->presenter->assignmentSolutions->get($solution->getId());
        Assert::false($solution->isAccepted());
    }

    public function testDownloadSolutionArchive()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $solution = current($this->presenter->assignmentSolutions->findAll());

        $mockFile = Mockery::mock(LocalImmutableFile::class);
        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("getSolutionFile")->with($solution->getSolution())->andReturn($mockFile)->once();
        $this->presenter->fileStorage = $mockFileStorage;

        $request = new Nette\Application\Request(
            'V1:AssignmentSolutions',
            'GET',
            ['action' => 'downloadSolutionArchive', 'id' => $solution->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(App\Responses\StorageFileResponse::class, $response);
        Assert::equal("solution-" . $solution->getId() . '.zip', $response->getName());
    }

    public function testDownloadResultArchive()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $submissions = $this->presenter->assignmentSolutionSubmissions->findAll();
        $submission = current(array_filter($submissions, function (AssignmentSolutionSubmission $sub) { return $sub->hasEvaluation(); }));

        $mockFile = Mockery::mock(LocalImmutableFile::class);
        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("getResultsArchive")->with($submission)->andReturn($mockFile)->once();
        $this->presenter->fileStorage = $mockFileStorage;

        $request = new Nette\Application\Request(
            'V1:AssignmentSolutions',
            'GET',
            ['action' => 'downloadResultArchive', 'submissionId' => $submission->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(App\Responses\StorageFileResponse::class, $response);
        Assert::equal("results-" . $submission->getId() . '.zip', $response->getName());
    }

    public function testDeleteAssignmentSolution()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        /** @var AssignmentSolution $solution */
        $solution = current($this->presenter->assignmentSolutions->findAll());
        $solutionId = $solution->getId();

        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $submissions = $solution->getSubmissions()->getValues();
        foreach ($submissions as $submission) {
            $mockFileStorage->shouldReceive("deleteResultsArchive")->with($submission)->once();
            $mockFileStorage->shouldReceive("deleteJobConfig")->with($submission)->once();    
        }
        $mockFileStorage->shouldReceive("deleteSolutionArchive")->with($solution->getSolution())->once();
        $this->presenter->fileStorage = $mockFileStorage;

        $request = new Nette\Application\Request(
            'V1:AssignmentSolutions', 'DELETE', [
            'action' => 'deleteSolution',
            'id' => $solution->getId()
        ]
        );

        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal('OK', $result['payload']);

        Assert::exception(
            function () use ($solutionId) {
                $this->presenter->assignmentSolutions->findOrThrow($solutionId);
            },
            NotFoundException::class
        );
    }

    //////////////////////////////////////////////////////////////////////////////

    private function getSupervisorWhoIsNotAuthorOrSuperadmin(Assignment $assignment, AssignmentSolution $solution)
    {
        return $assignment->getGroup()->getSupervisors()->filter(
            function (User $user) use ($solution) {
                return $solution->getSolution()->getAuthor() !== $user && $user->getRole() !== 'superadmin';
            }
        )->first();
    }

}

(new TestAssignmentSolutionsPresenter())->run();
