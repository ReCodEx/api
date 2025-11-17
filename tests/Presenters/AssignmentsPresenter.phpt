<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Helpers\Notifications\AssignmentEmailsSender;
use App\Model\Entity\Assignment;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseLimits;
use App\Model\Entity\Group;
use App\Model\Entity\HardwareGroup;
use App\Model\Entity\RuntimeEnvironment;
use App\Model\Repository\HardwareGroups;
use App\Model\Repository\RuntimeEnvironments;
use App\Model\Repository\SolutionEvaluations;
use App\Model\View\AssignmentSolutionViewFactory;
use App\Model\View\AssignmentViewFactory;
use App\Helpers\FileStorageManager;
use App\Helpers\FileStorage\LocalImmutableFile;
use App\Helpers\TmpFilesHelper;
use App\Helpers\FileStorage\LocalFileStorage;
use App\Helpers\FileStorage\LocalHashFileStorage;
use App\V1Module\Presenters\AssignmentsPresenter;
use App\Security\Roles;
use Doctrine\ORM\EntityManagerInterface;
use Tester\Assert;
use App\Helpers\JobConfig;
use App\Exceptions\NotFoundException;
use Nette\Http;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

/**
 * @testCase
 */
class TestAssignmentsPresenter extends Tester\TestCase
{
    /** @var AssignmentsPresenter */
    protected $presenter;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var  Nette\DI\Container */
    protected $container;

    /** @var App\Model\Repository\Assignments */
    protected $assignments;

    /** @var Nette\Security\User */
    private $user;

    /** @var RuntimeEnvironments */
    private $runtimeEnvironments;

    /** @var HardwareGroups */
    private $hardwareGroups;

    /** @var AssignmentSolutionViewFactory */
    private $assignmentSolutionViewFactory;

    /** @var Http\Request */
    private $originalHttpRequest;

    /** @var Http\Request|Mockery\Mock */
    private $mockHttpRequest;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);
        $this->user = $container->getByType(\Nette\Security\User::class);
        $this->assignments = $container->getByType(App\Model\Repository\Assignments::class);
        $this->runtimeEnvironments = $container->getByType(RuntimeEnvironments::class);
        $this->hardwareGroups = $container->getByType(HardwareGroups::class);
        $this->assignmentSolutionViewFactory = $container->getByType(AssignmentSolutionViewFactory::class);

        // patch container, since we cannot create actual file storage manager
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

        $this->originalHttpRequest = $this->container->getByType(Http\Request::class);
        $this->mockHttpRequest = Mockery::mock($this->originalHttpRequest);
        PresenterTestHelper::replaceService($this->container, $this->mockHttpRequest, Http\Request::class);

        $this->presenter = PresenterTestHelper::createPresenter($this->container, AssignmentsPresenter::class);
    }

    protected function tearDown()
    {
        Mockery::close();

        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
    }

    public function testDetail()
    {
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);

        $assignments = $this->assignments->findAll();
        $assignment = array_pop($assignments);

        $request = new Nette\Application\Request(
            'V1:Assignments',
            'GET',
            ['action' => 'detail', 'id' => $assignment->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal($assignment->getId(), $result['payload']["id"]);
    }

    public function testUpdateDetail()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $assignments = $this->assignments->findAll();
        $assignment = array_pop($assignments);
        $assignment->setIsPublic(false); // for testing of notification emails

        /** @var Mockery\Mock | AssignmentEmailsSender $mockAssignmentEmailsSender */
        $mockAssignmentEmailsSender = Mockery::mock(JobConfig\JobConfig::class);
        $mockAssignmentEmailsSender->shouldReceive("assignmentCreated")->with($assignment)->andReturn(true)->once();
        $this->presenter->assignmentEmailsSender = $mockAssignmentEmailsSender;

        $mockEvaluations = Mockery::mock(SolutionEvaluations::class);
        $mockEvaluations->shouldReceive("flush")->once();
        $this->presenter->solutionEvaluations = $mockEvaluations;

        $isPublic = true;
        $localizedTexts = [
            ["locale" => "locA", "text" => "descA", "name" => "nameA"]
        ];
        $firstDeadline = (new DateTime())->getTimestamp();
        $maxPointsBeforeFirstDeadline = 123;
        $submissionsCountLimit = 32;
        $allowSecondDeadline = true;
        $canViewLimitRatios = false;
        $canViewMeasuredValues = false;
        $canViewJudgeStdout = true;
        $canViewJudgeStderr = false;
        $secondDeadline = (new DateTime())->getTimestamp() + 10;
        $maxPointsBeforeSecondDeadline = 543;
        $visibleFrom = (new DateTime())->getTimestamp();
        $isBonus = true;
        $pointsPercentualThreshold = 90.0;
        $solutionFilesLimit = 3;
        $solutionSizeLimit = null;

        $request = new Nette\Application\Request(
            'V1:Assignments',
            'POST',
            ['action' => 'updateDetail', 'id' => $assignment->getId()],
            [
                'isPublic' => $isPublic,
                'version' => 1,
                'localizedTexts' => $localizedTexts,
                'firstDeadline' => $firstDeadline,
                'maxPointsBeforeFirstDeadline' => $maxPointsBeforeFirstDeadline,
                'submissionsCountLimit' => $submissionsCountLimit,
                'allowSecondDeadline' => $allowSecondDeadline,
                'maxPointsDeadlineInterpolation' => false,
                'canViewLimitRatios' => $canViewLimitRatios,
                'canViewMeasuredValues' => $canViewMeasuredValues,
                'canViewJudgeStdout' => $canViewJudgeStdout,
                'canViewJudgeStderr' => $canViewJudgeStderr,
                'secondDeadline' => $secondDeadline,
                'maxPointsBeforeSecondDeadline' => $maxPointsBeforeSecondDeadline,
                'visibleFrom' => $visibleFrom,
                'isBonus' => $isBonus,
                'pointsPercentualThreshold' => $pointsPercentualThreshold,
                'solutionFilesLimit' => $solutionFilesLimit,
                'solutionSizeLimit' => $solutionSizeLimit,
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        // check updated assignment
        /** @var Assignment $updatedAssignment */
        $updatedAssignment = $result['payload'];
        Assert::equal($isPublic, $updatedAssignment["isPublic"]);
        Assert::equal($firstDeadline, $updatedAssignment["firstDeadline"]);
        Assert::equal($maxPointsBeforeFirstDeadline, $updatedAssignment["maxPointsBeforeFirstDeadline"]);
        Assert::equal($submissionsCountLimit, $updatedAssignment["submissionsCountLimit"]);
        Assert::equal($allowSecondDeadline, $updatedAssignment["allowSecondDeadline"]);
        Assert::equal($canViewLimitRatios, $updatedAssignment["canViewLimitRatios"]);
        Assert::equal($canViewMeasuredValues, $updatedAssignment["canViewMeasuredValues"]);
        Assert::equal($canViewJudgeStdout, $updatedAssignment["canViewJudgeStdout"]);
        Assert::equal($canViewJudgeStderr, $updatedAssignment["canViewJudgeStderr"]);
        Assert::equal($secondDeadline, $updatedAssignment["secondDeadline"]);
        Assert::equal($maxPointsBeforeSecondDeadline, $updatedAssignment["maxPointsBeforeSecondDeadline"]);
        Assert::equal($visibleFrom, $updatedAssignment["visibleFrom"]);
        Assert::equal($isBonus, $updatedAssignment["isBonus"]);
        Assert::equal($pointsPercentualThreshold, $updatedAssignment["pointsPercentualThreshold"]);
        Assert::equal($solutionFilesLimit, $updatedAssignment['solutionFilesLimit']);
        Assert::equal($solutionSizeLimit, $updatedAssignment['solutionSizeLimit']);

        // check localized texts
        Assert::count(1, $updatedAssignment["localizedTexts"]);
        $localized = current($localizedTexts);
        $updatedLocalized = $updatedAssignment["localizedTexts"][0];
        Assert::equal($updatedLocalized["locale"], $localized["locale"]);
        Assert::equal($updatedLocalized["text"], $localized["text"]);
    }

    public function testUpdateDetailWithoutNotifications()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $assignments = $this->assignments->findAll();
        $assignment = array_pop($assignments);
        $assignment->setIsPublic(false); // for testing of notification emails

        /** @var Mockery\Mock | AssignmentEmailsSender $mockAssignmentEmailsSender */
        $mockAssignmentEmailsSender = Mockery::mock(JobConfig\JobConfig::class);
        $mockAssignmentEmailsSender->shouldReceive()->never(); // this is the main assertion of this test (no mail is sent)
        $this->presenter->assignmentEmailsSender = $mockAssignmentEmailsSender;

        $mockEvaluations = Mockery::mock(SolutionEvaluations::class);
        $mockEvaluations->shouldReceive("flush")->once();
        $this->presenter->solutionEvaluations = $mockEvaluations;

        $request = new Nette\Application\Request(
            'V1:Assignments',
            'POST',
            ['action' => 'updateDetail', 'id' => $assignment->getId()],
            [
                'isPublic' => true,
                'version' => 1,
                'sendNotification' => false,
                'localizedTexts' => [
                    ["locale" => "locA", "text" => "descA", "name" => "nameA"]
                ],
                'firstDeadline' => (new DateTime())->getTimestamp(),
                'maxPointsBeforeFirstDeadline' => 42,
                'submissionsCountLimit' => 10,
                'allowSecondDeadline' => false,
                'maxPointsDeadlineInterpolation' => true,
                'canViewLimitRatios' => false,
                'canViewMeasuredValues' => false,
                'canViewJudgeStdout' => false,
                'canViewJudgeStderr' => false,
                'isBonus' => false,
                'pointsPercentualThreshold' => 50.0,
                'solutionFilesLimit' => null,
                'solutionSizeLimit' => 42,
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        // check updated assignment
        /** @var Assignment $updatedAssignment */
        $updatedAssignment = $result['payload'];
        Assert::true($updatedAssignment["isPublic"]);
        Assert::false($updatedAssignment["maxPointsDeadlineInterpolation"]);
        Assert::equal(null, $updatedAssignment["solutionFilesLimit"]);
        Assert::equal(42, $updatedAssignment["solutionSizeLimit"]);
    }

    public function testUpdateDetailVisibleFromScheduleAsyncNotification()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $assignments = $this->assignments->findAll();
        $assignment = array_pop($assignments);
        $assignment->setIsPublic(false); // for testing of notification emails

        /** @var Mockery\Mock | AssignmentEmailsSender $mockAssignmentEmailsSender */
        //$mockAssignmentEmailsSender = Mockery::mock(JobConfig\JobConfig::class);
        //$mockAssignmentEmailsSender->shouldReceive("assignmentCreated")->with($assignment)->andReturn(true)->once();
        //$this->presenter->assignmentEmailsSender = $mockAssignmentEmailsSender;
        $mockDispatcher = Mockery::mock(\App\Async\Dispatcher::class);
        $mockDispatcher->shouldReceive("schedule")->once();
        $this->presenter->dispatcher = $mockDispatcher;

        $mockEvaluations = Mockery::mock(SolutionEvaluations::class);
        $mockEvaluations->shouldReceive("flush")->once();
        $this->presenter->solutionEvaluations = $mockEvaluations;

        $isPublic = true;
        $localizedTexts = [
            ["locale" => "locA", "text" => "descA", "name" => "nameA"]
        ];
        $firstDeadline = (new DateTime())->getTimestamp() + 100;
        $maxPointsBeforeFirstDeadline = 123;
        $submissionsCountLimit = 32;
        $allowSecondDeadline = true;
        $canViewLimitRatios = false;
        $canViewMeasuredValues = false;
        $canViewJudgeStdout = true;
        $canViewJudgeStderr = false;
        $secondDeadline = (new DateTime())->getTimestamp() + 1000;
        $maxPointsBeforeSecondDeadline = 543;
        $visibleFrom = (new DateTime())->getTimestamp() + 60;
        $isBonus = true;
        $pointsPercentualThreshold = 90.0;
        $solutionFilesLimit = 3;
        $solutionSizeLimit = null;

        $request = new Nette\Application\Request(
            'V1:Assignments',
            'POST',
            ['action' => 'updateDetail', 'id' => $assignment->getId()],
            [
                'isPublic' => $isPublic,
                'version' => 1,
                'sendNotification' => true,
                'localizedTexts' => $localizedTexts,
                'firstDeadline' => $firstDeadline,
                'maxPointsBeforeFirstDeadline' => $maxPointsBeforeFirstDeadline,
                'submissionsCountLimit' => $submissionsCountLimit,
                'allowSecondDeadline' => $allowSecondDeadline,
                'maxPointsDeadlineInterpolation' => false,
                'canViewLimitRatios' => $canViewLimitRatios,
                'canViewMeasuredValues' => $canViewMeasuredValues,
                'canViewJudgeStdout' => $canViewJudgeStdout,
                'canViewJudgeStderr' => $canViewJudgeStderr,
                'secondDeadline' => $secondDeadline,
                'maxPointsBeforeSecondDeadline' => $maxPointsBeforeSecondDeadline,
                'visibleFrom' => $visibleFrom,
                'isBonus' => $isBonus,
                'pointsPercentualThreshold' => $pointsPercentualThreshold,
                'solutionFilesLimit' => $solutionFilesLimit,
                'solutionSizeLimit' => $solutionSizeLimit,
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        // check updated assignment
        /** @var Assignment $updatedAssignment */
        $updatedAssignment = $result['payload'];
        Assert::equal($isPublic, $updatedAssignment["isPublic"]);
        Assert::equal($firstDeadline, $updatedAssignment["firstDeadline"]);
        Assert::equal($maxPointsBeforeFirstDeadline, $updatedAssignment["maxPointsBeforeFirstDeadline"]);
        Assert::equal($submissionsCountLimit, $updatedAssignment["submissionsCountLimit"]);
        Assert::equal($allowSecondDeadline, $updatedAssignment["allowSecondDeadline"]);
        Assert::equal($canViewLimitRatios, $updatedAssignment["canViewLimitRatios"]);
        Assert::equal($canViewMeasuredValues, $updatedAssignment["canViewMeasuredValues"]);
        Assert::equal($canViewJudgeStdout, $updatedAssignment["canViewJudgeStdout"]);
        Assert::equal($canViewJudgeStderr, $updatedAssignment["canViewJudgeStderr"]);
        Assert::equal($secondDeadline, $updatedAssignment["secondDeadline"]);
        Assert::equal($maxPointsBeforeSecondDeadline, $updatedAssignment["maxPointsBeforeSecondDeadline"]);
        Assert::equal($visibleFrom, $updatedAssignment["visibleFrom"]);
        Assert::equal($isBonus, $updatedAssignment["isBonus"]);
        Assert::equal($pointsPercentualThreshold, $updatedAssignment["pointsPercentualThreshold"]);
        Assert::equal($solutionFilesLimit, $updatedAssignment['solutionFilesLimit']);
        Assert::equal($solutionSizeLimit, $updatedAssignment['solutionSizeLimit']);

        // check localized texts
        Assert::count(1, $updatedAssignment["localizedTexts"]);
        $localized = current($localizedTexts);
        $updatedLocalized = $updatedAssignment["localizedTexts"][0];
        Assert::equal($updatedLocalized["locale"], $localized["locale"]);
        Assert::equal($updatedLocalized["text"], $localized["text"]);
    }

    public function testAddStudentHints()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $assignments = $this->assignments->findAll();
        /** @var Assignment $assignment */
        $assignment = array_pop($assignments);
        $disabledEnv = $assignment->getRuntimeEnvironments()->first();

        $request = new Nette\Application\Request(
            'V1:Assignments',
            'POST',
            ['action' => 'updateDetail', 'id' => $assignment->getId()],
            [
                'isPublic' => true,
                'version' => 1,
                'localizedTexts' => [
                    ["locale" => "locA", "text" => "descA", "name" => "nameA", "studentHint" => "Try hard"]
                ],
                'firstDeadline' => (new DateTime())->getTimestamp(),
                'maxPointsBeforeFirstDeadline' => 123,
                'submissionsCountLimit' => 32,
                'allowSecondDeadline' => true,
                'maxPointsDeadlineInterpolation' => true,
                'canViewLimitRatios' => false,
                'canViewMeasuredValues' => false,
                'canViewJudgeStdout' => false,
                'canViewJudgeStderr' => false,
                'secondDeadline' => (new DateTime())->getTimestamp() + 10,
                'maxPointsBeforeSecondDeadline' => 543,
                'isBonus' => true,
                'pointsPercentualThreshold' => 90.0,
                'solutionFilesLimit' => 3,
                'solutionSizeLimit' => 42,
                'disabledRuntimeEnvironmentIds' => [$disabledEnv->getId()]
            ]
        );

        $response = $this->presenter->run($request);
        $updatedAssignment = PresenterTestHelper::extractPayload($response);
        Assert::count(1, $updatedAssignment["localizedTexts"]);
        Assert::equal("locA", $updatedAssignment["localizedTexts"][0]["locale"]);
        Assert::equal("Try hard", $updatedAssignment["localizedTexts"][0]["studentHint"]);
        Assert::true($updatedAssignment["maxPointsDeadlineInterpolation"]);
    }

    public function testDisableRuntimeEnvironments()
    {
        $this->mockHttpRequest->shouldReceive("getHeader")->andReturn("application/json");
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $assignments = $this->assignments->findAll();
        /** @var Assignment $assignment */
        $assignment = array_pop($assignments);
        $disabledEnv = $assignment->getRuntimeEnvironments()->first();

        $request = new Nette\Application\Request(
            'V1:Assignments',
            'POST',
            ['action' => 'updateDetail', 'id' => $assignment->getId()],
            [
                'isPublic' => true,
                'version' => 1,
                'localizedTexts' => [
                    ["locale" => "locA", "text" => "descA", "name" => "nameA"]
                ],
                'firstDeadline' => (new DateTime())->getTimestamp(),
                'maxPointsBeforeFirstDeadline' => 123,
                'submissionsCountLimit' => 32,
                'allowSecondDeadline' => true,
                'maxPointsDeadlineInterpolation' => false,
                'canViewLimitRatios' => false,
                'canViewMeasuredValues' => false,
                'canViewJudgeStdout' => false,
                'canViewJudgeStderr' => false,
                'secondDeadline' => (new DateTime())->getTimestamp() + 10,
                'maxPointsBeforeSecondDeadline' => 543,
                'isBonus' => true,
                'pointsPercentualThreshold' => 90.0,
                'solutionFilesLimit' => null,
                'solutionSizeLimit' => null,
                'disabledRuntimeEnvironmentIds' => [$disabledEnv->getId()]
            ]
        );

        $response = $this->presenter->run($request);
        $updatedAssignment = PresenterTestHelper::extractPayload($response);

        Assert::same([$disabledEnv->getId()], $updatedAssignment["disabledRuntimeEnvironmentIds"]);
        Assert::true(in_array($disabledEnv->getId(), $updatedAssignment["runtimeEnvironmentIds"]));
    }

    public function testSetVisibilityFlags()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $assignments = $this->assignments->findAll();
        /** @var Assignment $assignment */
        $assignment = array_pop($assignments);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Assignments',
            'POST',
            ['action' => 'updateDetail', 'id' => $assignment->getId()],
            [
                'isPublic' => true,
                'version' => 1,
                'localizedTexts' => [
                    ["locale" => "locA", "text" => "descA", "name" => "nameA"]
                ],
                'firstDeadline' => (new DateTime())->getTimestamp(),
                'maxPointsBeforeFirstDeadline' => 123,
                'submissionsCountLimit' => 32,
                'allowSecondDeadline' => true,
                'maxPointsDeadlineInterpolation' => false,
                'canViewLimitRatios' => true,
                'canViewMeasuredValues' => true,
                'canViewJudgeStdout' => true,
                'canViewJudgeStderr' => true,
                'secondDeadline' => (new DateTime())->getTimestamp() + 10,
                'maxPointsBeforeSecondDeadline' => 543,
                'isBonus' => true,
                'pointsPercentualThreshold' => 90.0,
                'solutionFilesLimit' => null,
                'solutionSizeLimit' => null,
                'isExam' => false,
            ]
        );

        Assert::true($payload["canViewLimitRatios"]);
        Assert::true($payload["canViewMeasuredValues"]);
        Assert::true($payload["canViewJudgeStdout"]);
        Assert::true($payload["canViewJudgeStderr"]);
        $this->presenter->assignments->refresh($assignment);
        Assert::true($assignment->getCanViewLimitRatios());
        Assert::true($assignment->getCanViewMeasuredValues());
        Assert::true($assignment->getCanViewJudgeStdout());
        Assert::true($assignment->getCanViewJudgeStderr());
    }

    public function testSetExamFlag()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $assignments = $this->assignments->findAll();
        /** @var Assignment $assignment */
        $assignment = array_pop($assignments);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Assignments',
            'POST',
            ['action' => 'updateDetail', 'id' => $assignment->getId()],
            [
                'isPublic' => true,
                'version' => 1,
                'localizedTexts' => [
                    ["locale" => "locA", "text" => "descA", "name" => "nameA"]
                ],
                'firstDeadline' => (new DateTime())->getTimestamp(),
                'maxPointsBeforeFirstDeadline' => 123,
                'submissionsCountLimit' => 32,
                'allowSecondDeadline' => true,
                'maxPointsDeadlineInterpolation' => false,
                'canViewLimitRatios' => false,
                'canViewMeasuredValues' => false,
                'canViewJudgeStdout' => false,
                'canViewJudgeStderr' => false,
                'secondDeadline' => (new DateTime())->getTimestamp() + 10,
                'maxPointsBeforeSecondDeadline' => 543,
                'isBonus' => true,
                'pointsPercentualThreshold' => 90.0,
                'solutionFilesLimit' => null,
                'solutionSizeLimit' => null,
                'isExam' => true,
            ]
        );

        Assert::true($payload["isExam"]);
        $this->presenter->assignments->refresh($assignment);
        Assert::true($assignment->isExam());
    }

    public function testUnsetExamFlag()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $assignments = $this->assignments->findAll();
        /** @var Assignment $assignment */
        $assignment = array_pop($assignments);
        $assignment->setExam();
        $this->presenter->assignments->persist($assignment);
        Assert::true($assignment->isExam());


        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Assignments',
            'POST',
            ['action' => 'updateDetail', 'id' => $assignment->getId()],
            [
                'isPublic' => true,
                'version' => 1,
                'localizedTexts' => [
                    ["locale" => "locA", "text" => "descA", "name" => "nameA"]
                ],
                'firstDeadline' => (new DateTime())->getTimestamp(),
                'maxPointsBeforeFirstDeadline' => 123,
                'submissionsCountLimit' => 32,
                'allowSecondDeadline' => true,
                'maxPointsDeadlineInterpolation' => false,
                'canViewLimitRatios' => false,
                'canViewMeasuredValues' => false,
                'canViewJudgeStdout' => false,
                'canViewJudgeStderr' => false,
                'secondDeadline' => (new DateTime())->getTimestamp() + 10,
                'maxPointsBeforeSecondDeadline' => 543,
                'isBonus' => true,
                'pointsPercentualThreshold' => 90.0,
                'solutionFilesLimit' => null,
                'solutionSizeLimit' => null,
                'isExam' => false,
            ]
        );

        Assert::false($payload["isExam"]);
        $this->presenter->assignments->refresh($assignment);
        Assert::false($assignment->isExam());
    }

    public function testCreateAssignment()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $exercises = array_filter(
            $this->presenter->exercises->findAll(),
            function (Exercise $e) {
                return !$e->getFileLinks()->isEmpty(); // select the exercise with file links
            }
        );
        Assert::count(1, $exercises);
        /** @var Exercise $exercise */
        $exercise = array_pop($exercises);

        // original links of the exercise indexed by keys
        $exerciseLinks = [];
        foreach ($exercise->getFileLinks() as $link) {
            $exerciseLinks[$link->getKey()] = $link;
        }

        /** @var Group $group */
        $group = $this->presenter->groups->findAll()[0];

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Assignments',
            'POST',
            ['action' => 'create'],
            ['exerciseId' => $exercise->getId(), 'groupId' => $group->getId()]
        );

        /** @var AssignmentViewFactory $viewFactory */
        $viewFactory = $this->container->getByType(AssignmentViewFactory::class);

        // Make sure the assignment was persisted
        Assert::same(
            $viewFactory->getAssignment($this->presenter->assignments->findOneBy(['id' => $payload["id"]])),
            $payload
        );
        Assert::count(count($exerciseLinks), $payload['localizedTextsLinks']);
        foreach ($payload['localizedTextsLinks'] as $key => $linkId) {
            Assert::true(array_key_exists($key, $exerciseLinks));
            Assert::notEqual($exerciseLinks[$key]->getId(), $linkId); // new link should be created
        }

        // verify the newly created file links in the assignment
        $assignment = $this->presenter->assignments->get($payload["id"]);
        Assert::count(count($exerciseLinks), $assignment->getFileLinks());
        foreach ($assignment->getFileLinks() as $link) {
            Assert::true(array_key_exists($link->getKey(), $exerciseLinks));
            $origLink = $exerciseLinks[$link->getKey()];
            Assert::notSame($origLink->getId(), $link->getId());
            Assert::null($link->getExercise());
            Assert::equal($origLink->getExerciseFile()->getId(), $link->getExerciseFile()->getId());
            Assert::equal($origLink->getSaveName(), $link->getSaveName());
            Assert::equal($origLink->getRequiredRole(), $link->getRequiredRole());
        }
    }

    public function testCreateAssignmentFromLockedExercise()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        /** @var Exercise $exercise */
        $exercise = $this->presenter->exercises->findAll()[0];
        /** @var Group $group */
        $group = $this->presenter->groups->findAll()[0];

        $exercise->setLocked(true);
        $this->presenter->exercises->flush();

        $request = new Nette\Application\Request(
            'V1:Assignments',
            'POST',
            ['action' => 'create'],
            ['exerciseId' => $exercise->getId(), 'groupId' => $group->getId()]
        );

        Assert::exception(
            function () use ($request) {
                $this->presenter->run($request);
            },
            App\Exceptions\BadRequestException::class
        );
    }

    public function testCreateAssignmentFromArchivedExercise()
    {
        PresenterTestHelper::login($this->container, PresenterTestHelper::GROUP_SUPERVISOR_LOGIN);

        /** @var Exercise $exercise */
        $exercise = array_values(array_filter($this->presenter->exercises->findAll(), function ($e) {
            return $e->isArchived();
        }))[0];
        /** @var Group $group */
        $group = $this->presenter->groups->findAll()[0];

        $request = new Nette\Application\Request(
            'V1:Assignments',
            'POST',
            ['action' => 'create'],
            ['exerciseId' => $exercise->getId(), 'groupId' => $group->getId()]
        );

        Assert::exception(
            function () use ($request) {
                $this->presenter->run($request);
            },
            App\Exceptions\ForbiddenRequestException::class
        );
    }

    public function testCreateAssignmentInOrganizationalGroup()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        /** @var Exercise $exercise */
        $exercise = $this->presenter->exercises->findAll()[0];
        /** @var Group $group */
        $group = $this->presenter->groups->findAll()[0];

        $group->setOrganizational(true);
        $this->presenter->groups->flush();

        $request = new Nette\Application\Request(
            'V1:Assignments',
            'POST',
            ['action' => 'create'],
            ['exerciseId' => $exercise->getId(), 'groupId' => $group->getId()]
        );

        Assert::exception(
            function () use ($request) {
                $this->presenter->run($request);
            },
            App\Exceptions\BadRequestException::class
        );
    }

    public function testSyncWithExercise()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = PresenterTestHelper::getUser($this->container);

        /** @var RuntimeEnvironment $environment */
        $environment = $this->runtimeEnvironments->findAll()[0];
        /** @var HardwareGroup $hwGroup */
        $hwGroup = $this->hardwareGroups->findAll()[0];
        /** @var Group $group */
        $group = $this->presenter->groups->findAll()[0];

        $limits = "
      memory: 42,
      wall-time: 33
    ";

        $newLimits = "
      memory: 33,
      wall-time: 44
    ";

        $exercises = array_filter(
            $this->presenter->exercises->findAll(),
            function (Exercise $e) {
                return !$e->getFileLinks()->isEmpty(); // select the exercise with file links
            }
        );
        Assert::count(1, $exercises);
        /** @var Exercise $exercise */
        $exercise = array_pop($exercises);

        $exerciseLimits = new ExerciseLimits($environment, $hwGroup, $limits, $user);
        $this->em->persist($exerciseLimits);

        $exercise->addExerciseLimits($exerciseLimits);
        $assignment = Assignment::assignToGroup($exercise, $group);
        $this->em->persist($assignment);
        $this->em->flush();

        $newExerciseLimits = new ExerciseLimits($environment, $hwGroup, $newLimits, $user);
        $this->em->persist($newExerciseLimits);
        $exercise->clearExerciseLimits();
        $exercise->addExerciseLimits($newExerciseLimits);

        $exercise->getFileLinks()->removeElement($exercise->getFileLinks()->first());
        Assert::count(1, $exercise->getFileLinks());
        $link = $exercise->getFileLinks()->first();
        $link->setKey("NEW");
        $link->setRequiredRole(Roles::SUPERVISOR_ROLE);
        $this->em->persist($link);
        $this->em->persist($exercise);
        $this->em->flush();

        $request = new Nette\Application\Request(
            'V1:Assignments',
            'POST',
            ['action' => 'syncWithExercise', 'id' => $assignment->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);
        $payload = $response->getPayload();
        $data = $payload["payload"];

        Assert::same($assignment->getId(), $data["id"]);
        Assert::same($newExerciseLimits, $assignment->getLimitsByEnvironmentAndHwGroup($environment, $hwGroup));
        Assert::count(1, $assignment->getFileLinks());
        $newLink = $assignment->getFileLinks()->first();
        Assert::equal("NEW", $newLink->getKey());
        Assert::equal(Roles::SUPERVISOR_ROLE, $newLink->getRequiredRole());
        Assert::equal($link->getExerciseFile()->getId(), $newLink->getExerciseFile()->getId());
        Assert::null($newLink->getExercise());
    }

    public function testRemove()
    {
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);

        $assignment = current($this->assignments->findAll());

        $request = new Nette\Application\Request(
            'V1:Assignments',
            'DELETE',
            ['action' => 'remove', 'id' => $assignment->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal("OK", $result['payload']);
        Assert::exception(
            function () use ($assignment) {
                $this->assignments->findOrThrow($assignment->getId());
            },
            NotFoundException::class
        );
    }

    public function testSolutions()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $solution = current($this->presenter->assignmentSolutions->findAll());
        $assignment = $solution->getAssignment();
        $solutions = $assignment->getAssignmentSolutions()->getValues();
        $solutions = array_map(
            function (AssignmentSolution $solution) {
                return $this->assignmentSolutionViewFactory->getSolutionData($solution);
            },
            $solutions
        );

        $request = new Nette\Application\Request(
            'V1:Assignments',
            'GET',
            ['action' => 'solutions', 'id' => $assignment->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::count(count($solutions), $result['payload']);
        Assert::same($solutions, $result['payload']);
    }

    public function testUserSolutions()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $solution = current($this->presenter->assignmentSolutions->findAll());
        $user = $solution->getSolution()->getAuthor();
        $assignment = $solution->getAssignment();
        $solutions = $this->presenter->assignmentSolutions->findSolutions($assignment, $user);
        $solutions = array_map(
            function (AssignmentSolution $solution) {
                return $this->assignmentSolutionViewFactory->getSolutionData($solution);
            },
            $solutions
        );

        $request = new Nette\Application\Request(
            'V1:Assignments',
            'GET',
            ['action' => 'userSolutions', 'id' => $assignment->getId(), 'userId' => $user->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::count(count($solutions), $result['payload']);
        Assert::same($solutions, $result['payload']);
    }

    public function testBestSolution()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $assignments = $this->presenter->assignments->findAll();
        foreach ($assignments as $assignment) {
            $assignmentSolutions = $assignment->getAssignmentSolutions()->toArray();
            foreach ($assignmentSolutions as $baseSolution) {
                $user = $baseSolution->getSolution()->getAuthor();
                $best = $this->presenter->assignmentSolutions->findBestSolution($assignment, $user);

                $request = new Nette\Application\Request(
                    'V1:Assignments',
                    'GET',
                    ['action' => 'bestSolution', 'id' => $assignment->getId(), 'userId' => $user->getId()]
                );
                $response = $this->presenter->run($request);
                Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

                $result = $response->getPayload();
                Assert::equal(200, $result['code']);

                $payload = $result['payload'];
                if ($best) {
                    Assert::equal($best->getId(), $payload['id']);
                } else {
                    Assert::equal(null, $payload);
                }
            }
        }
    }

    public function testBestSolutions()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $assignment = current($this->presenter->assignments->findAll());
        $users = $assignment->getGroup()->getStudents();

        $request = new Nette\Application\Request(
            'V1:Assignments',
            'GET',
            ['action' => 'bestSolutions', 'id' => $assignment->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        $payload = $result['payload'];
        Assert::count(count($users), $payload);
    }

    public function testDownloadSolutionArchive()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $assignment = current($this->presenter->assignments->findAll());

        $mockFile = Mockery::mock(LocalImmutableFile::class);
        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("getSolutionFile")->andReturn($mockFile)->atLeast(1);
        $this->presenter->fileStorage = $mockFileStorage;

        $request = new Nette\Application\Request(
            'V1:Assignments',
            'GET',
            ['action' => 'downloadBestSolutionsArchive', 'id' => $assignment->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(App\Responses\ZipFilesResponse::class, $response);

        // Check invariants
        Assert::equal("assignment-" . $assignment->getId() . '.zip', $response->getName());
    }
}

$testCase = new TestAssignmentsPresenter();
$testCase->run();
