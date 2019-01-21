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
use App\V1Module\Presenters\AssignmentsPresenter;
use Nette\Utils\Json;
use Tester\Assert;
use App\Helpers\JobConfig;
use App\Exceptions\NotFoundException;
use Nette\Http;


/**
 * @testCase
 */
class TestAssignmentsPresenter extends Tester\TestCase
{
  /** @var AssignmentsPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
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

    $request = new Nette\Application\Request('V1:Assignments', 'GET',
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
      [ "locale" => "locA", "text" => "descA", "name" => "nameA" ]
    ];
    $firstDeadline = (new \DateTime())->getTimestamp();
    $maxPointsBeforeFirstDeadline = 123;
    $submissionsCountLimit = 321;
    $allowSecondDeadline = true;
    $canViewLimitRatios = false;
    $secondDeadline = (new \DateTime())->getTimestamp();
    $maxPointsBeforeSecondDeadline = 543;
    $visibleFrom = (new \DateTime())->getTimestamp();
    $isBonus = true;
    $pointsPercentualThreshold = 90.0;

    $request = new Nette\Application\Request('V1:Assignments', 'POST',
      ['action' => 'updateDetail', 'id' => $assignment->getId()],
      [
        'isPublic' => $isPublic,
        'version' => 1,
        'localizedTexts' => $localizedTexts,
        'firstDeadline' => $firstDeadline,
        'maxPointsBeforeFirstDeadline' => $maxPointsBeforeFirstDeadline,
        'submissionsCountLimit' => $submissionsCountLimit,
        'allowSecondDeadline' => $allowSecondDeadline,
        'canViewLimitRatios' => $canViewLimitRatios,
        'secondDeadline' => $secondDeadline,
        'maxPointsBeforeSecondDeadline' => $maxPointsBeforeSecondDeadline,
        'visibleFrom' => $visibleFrom,
        'isBonus' => $isBonus,
        'pointsPercentualThreshold' => $pointsPercentualThreshold,
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
    Assert::equal($secondDeadline, $updatedAssignment["secondDeadline"]);
    Assert::equal($maxPointsBeforeSecondDeadline, $updatedAssignment["maxPointsBeforeSecondDeadline"]);
    Assert::equal($visibleFrom, $updatedAssignment["visibleFrom"]);
    Assert::equal($isBonus, $updatedAssignment["isBonus"]);
    Assert::equal($pointsPercentualThreshold, $updatedAssignment["pointsPercentualThreshold"]);

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

    $request = new Nette\Application\Request('V1:Assignments', 'POST',
      ['action' => 'updateDetail', 'id' => $assignment->getId()],
      [
        'isPublic' => true,
        'version' => 1,
        'sendNotification' => false,
        'localizedTexts' => [
          [ "locale" => "locA", "text" => "descA", "name" => "nameA" ]
        ],
        'firstDeadline' => (new \DateTime())->getTimestamp(),
        'maxPointsBeforeFirstDeadline' => 42,
        'submissionsCountLimit' => 10,
        'allowSecondDeadline' => false,
        'canViewLimitRatios' => false,
        'isBonus' => false,
        'pointsPercentualThreshold' => 50.0,
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
  }

  public function testAddStudentHints()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    $assignments = $this->assignments->findAll();
    /** @var Assignment $assignment */
    $assignment = array_pop($assignments);
    $disabledEnv = $assignment->getRuntimeEnvironments()->first();

    $request = new Nette\Application\Request('V1:Assignments', 'POST',
      ['action' => 'updateDetail', 'id' => $assignment->getId()],
      [
        'isPublic' => true,
        'version' => 1,
        'localizedTexts' => [
          ["locale" => "locA", "text" => "descA", "name" => "nameA", "studentHint" => "Try hard"]
        ],
        'firstDeadline' => (new \DateTime())->getTimestamp(),
        'maxPointsBeforeFirstDeadline' => 123,
        'submissionsCountLimit' => 321,
        'allowSecondDeadline' => true,
        'canViewLimitRatios' => false,
        'secondDeadline' => (new \DateTime())->getTimestamp(),
        'maxPointsBeforeSecondDeadline' => 543,
        'isBonus' => true,
        'pointsPercentualThreshold' => 90.0,
        'disabledRuntimeEnvironmentIds' => [$disabledEnv->getId()]
      ]
    );

    $response = $this->presenter->run($request);
    $updatedAssignment = PresenterTestHelper::extractPayload($response);
    Assert::count(1, $updatedAssignment["localizedTexts"]);
    Assert::equal("locA", $updatedAssignment["localizedTexts"][0]["locale"]);
    Assert::equal("Try hard", $updatedAssignment["localizedTexts"][0]["studentHint"]);
  }

  public function testDisableRuntimeEnvironments()
  {
    $this->mockHttpRequest->shouldReceive("getHeader")->andReturn("application/json");
    PresenterTestHelper::loginDefaultAdmin($this->container);

    $assignments = $this->assignments->findAll();
    /** @var Assignment $assignment */
    $assignment = array_pop($assignments);
    $disabledEnv = $assignment->getRuntimeEnvironments()->first();

    $request = new Nette\Application\Request('V1:Assignments', 'POST',
      ['action' => 'updateDetail', 'id' => $assignment->getId()],
      [
        'isPublic' => true,
        'version' => 1,
        'localizedTexts' => [
          [ "locale" => "locA", "text" => "descA", "name" => "nameA" ]
        ],
        'firstDeadline' => (new \DateTime())->getTimestamp(),
        'maxPointsBeforeFirstDeadline' => 123,
        'submissionsCountLimit' => 321,
        'allowSecondDeadline' => true,
        'canViewLimitRatios' => false,
        'secondDeadline' => (new \DateTime())->getTimestamp(),
        'maxPointsBeforeSecondDeadline' => 543,
        'isBonus' => true,
        'pointsPercentualThreshold' => 90.0,
        'disabledRuntimeEnvironmentIds' => [$disabledEnv->getId()]
      ]
    );

    $response = $this->presenter->run($request);
    $updatedAssignment = PresenterTestHelper::extractPayload($response);

    Assert::same([$disabledEnv->getId()], $updatedAssignment["disabledRuntimeEnvironmentIds"]);
    Assert::true(in_array($disabledEnv->getId(), $updatedAssignment["runtimeEnvironmentIds"]));
  }

  public function testCreateAssignment()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    $exercise = $this->presenter->exercises->findAll()[0];
    $group = $this->presenter->groups->findAll()[0];

    $request = new Nette\Application\Request(
      'V1:Assignments',
      'POST',
      ['action' => 'create'],
      ['exerciseId' => $exercise->id, 'groupId' => $group->id]
    );

    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    /** @var AssignmentViewFactory $viewFactory */
    $viewFactory = $this->container->getByType(AssignmentViewFactory::class);

    // Make sure the assignment was persisted
    Assert::same(
      $viewFactory->getAssignment($this->presenter->assignments->findOneBy(['id' => $result['payload']["id"]])),
      $result['payload']
    );
  }

  public function testCreateAssignmentFromLockedExercise()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    /** @var Exercise $exercise */
    $exercise = $this->presenter->exercises->findAll()[0];
    $group = $this->presenter->groups->findAll()[0];

    $exercise->setLocked(true);
    $this->presenter->exercises->flush();

    $request = new Nette\Application\Request(
      'V1:Assignments',
      'POST',
      ['action' => 'create'],
      ['exerciseId' => $exercise->id, 'groupId' => $group->id]
    );

    Assert::exception(function () use ($request) {
      $this->presenter->run($request);
    }, App\Exceptions\BadRequestException::class);
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

    Assert::exception(function () use ($request) {
      $this->presenter->run($request);
    }, App\Exceptions\BadRequestException::class);
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

    /** @var Exercise $exercise */
    $exercise = $this->presenter->exercises->findAll()[0];
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

    $this->em->flush();

    $request = new Nette\Application\Request('V1:Assignments', 'POST',
      ['action' => 'syncWithExercise', 'id' => $assignment->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);
    $payload = $response->getPayload();
    $data = $payload["payload"];

    Assert::same($assignment->getId(), $data["id"]);
    Assert::same($newExerciseLimits, $assignment->getLimitsByEnvironmentAndHwGroup($environment, $hwGroup));
  }

  public function testRemove()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $assignment = current($this->assignments->findAll());

    $request = new Nette\Application\Request('V1:Assignments', 'DELETE',
      ['action' => 'remove', 'id' => $assignment->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal("OK", $result['payload']);
    Assert::exception(function () use ($assignment) {
      $this->assignments->findOrThrow($assignment->getId());
    }, NotFoundException::class);
  }

  public function testSolutions()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    $solution = current($this->presenter->assignmentSolutions->findAll());
    $assignment = $solution->getAssignment();
    $solutions = $assignment->getAssignmentSolutions()->getValues();
    $solutions = array_map(function (AssignmentSolution $solution) {
      return $this->assignmentSolutionViewFactory->getSolutionData($solution);
    }, $solutions);

    $request = new Nette\Application\Request('V1:Assignments', 'GET',
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
    $solutions = array_map(function (AssignmentSolution $solution) {
      return $this->assignmentSolutionViewFactory->getSolutionData($solution);
    }, $solutions);

    $request = new Nette\Application\Request('V1:Assignments', 'GET',
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

    $assignment = current($this->presenter->assignments->findAll());
    $user = $assignment->getAssignmentSolutions()->first()->getSolution()->getAuthor();
    $submission = $this->presenter->assignmentSolutions->findBestSolution($assignment, $user);

    $request = new Nette\Application\Request('V1:Assignments', 'GET',
      ['action' => 'bestSolution', 'id' => $assignment->getId(), 'userId' => $user->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    $payload = $result['payload'];
    Assert::equal($submission->getId(), $payload['id']);
  }

  public function testBestSolutions()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    $assignment = current($this->presenter->assignments->findAll());
    $users = $assignment->getGroup()->getStudents();

    $request = new Nette\Application\Request('V1:Assignments', 'GET',
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

    $request = new Nette\Application\Request('V1:Assignments', 'GET',
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
