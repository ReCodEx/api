<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Helpers\Notifications\AssignmentEmailsSender;
use App\Model\Entity\Assignment;
use App\V1Module\Presenters\ShadowAssignmentsPresenter;
use Tester\Assert;
use App\Helpers\JobConfig;
use App\Exceptions\NotFoundException;


/**
 * @testCase
 */
class TestShadowAssignmentsPresenter extends Tester\TestCase
{
  /** @var ShadowAssignmentsPresenter */
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
    $this->presenter = PresenterTestHelper::createPresenter($this->container, ShadowAssignmentsPresenter::class);
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
    PresenterTestHelper::loginDefaultAdmin($this->container);
    $assignment = current($this->presenter->shadowAssignments->findAll());

    $request = new Nette\Application\Request('V1:ShadowAssignments', 'GET',
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
    $assignment = current($this->presenter->shadowAssignments->findAll());
    $assignment->setIsPublic(false); // for testing of notification emails

    /** @var Mockery\Mock | AssignmentEmailsSender $mockAssignmentEmailsSender */
    $mockAssignmentEmailsSender = Mockery::mock(JobConfig\JobConfig::class);
    $mockAssignmentEmailsSender->shouldReceive("assignmentCreated")->with($assignment)->andReturn(true)->once();
    $this->presenter->assignmentEmailsSender = $mockAssignmentEmailsSender;

    $isPublic = true;
    $localizedTexts = [
      [ "locale" => "locA", "text" => "descA", "name" => "nameA" ]
    ];
    $maxPoints = 123;
    $isBonus = true;

    $request = new Nette\Application\Request('V1:ShadowAssignments', 'POST',
      ['action' => 'updateDetail', 'id' => $assignment->getId()],
      [
        'version' => 1,
        'isPublic' => $isPublic,
        'isBonus' => $isBonus,
        'localizedTexts' => $localizedTexts,
        'maxPoints' => $maxPoints,
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    // check updated assignment
    /** @var Assignment $updatedAssignment */
    $updatedAssignment = $result['payload'];
    Assert::equal(2, $updatedAssignment["version"]);
    Assert::equal($isPublic, $updatedAssignment["isPublic"]);
    Assert::equal($maxPoints, $updatedAssignment["maxPoints"]);
    Assert::equal($isBonus, $updatedAssignment["isBonus"]);

    // check localized texts
    Assert::count(1, $updatedAssignment["localizedTexts"]);
    $localized = current($localizedTexts);
    $updatedLocalized = $updatedAssignment["localizedTexts"][0];
    Assert::equal($updatedLocalized["locale"], $localized["locale"]);
    Assert::equal($updatedLocalized["text"], $localized["text"]);
  }

  public function testCreate()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);
    $group = $this->presenter->groups->findAll()[0];

    $request = new Nette\Application\Request(
      'V1:ShadowAssignments',
      'POST',
      ['action' => 'create'],
      ['groupId' => $group->id]
    );

    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    $assignment = $result['payload'];
    Assert::equal($group->getId(), $assignment["groupId"]);
    Assert::equal(1, $assignment["version"]);
    Assert::equal(false, $assignment["isPublic"]);
    Assert::equal(false, $assignment["isBonus"]);
  }

  public function testRemove()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);
    $assignmentId = current($this->presenter->shadowAssignments->findAll())->getId();

    $request = new Nette\Application\Request('V1:ShadowAssignments', 'DELETE',
      ['action' => 'remove', 'id' => $assignmentId]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal("OK", $result['payload']);
    Assert::exception(function () use ($assignmentId) {
      $this->presenter->shadowAssignments->findOrThrow($assignmentId);
    }, NotFoundException::class);
  }

  public function testCreatePoints()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);
    $assignment = current($this->presenter->shadowAssignments->findAll());
    $user = PresenterTestHelper::getUser($this->container, PresenterTestHelper::STUDENT_GROUP_MEMBER_LOGIN);
    $timestamp = time();

    $request = new Nette\Application\Request(
      'V1:ShadowAssignments',
      'POST',
      ['action' => 'createPoints', 'id' => $assignment->getId()],
      [
        'userId' => $user->getId(),
        'points' => 1287,
        'note' => "some testing note",
        'awardedAt' => $timestamp
      ]
    );

    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    $points = $result['payload'];
    Assert::equal(1287, $points['points']);
    Assert::equal("some testing note", $points['note']);
    Assert::equal($this->user->getId(), $points['authorId']);
    Assert::equal($user->getId(), $points['awardeeId']);
    Assert::equal($timestamp, $points['awardedAt']);
  }

  public function testUpdatePoints()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);
    $shadowPoints = current($this->presenter->shadowAssignmentPointsRepository->findAll());
    $timestamp = time();

    $request = new Nette\Application\Request(
      'V1:ShadowAssignments',
      'POST',
      ['action' => 'updatePoints', 'pointsId' => $shadowPoints->getId()],
      [
        'points' => 147,
        'note' => "update some testing note",
        'awardedAt' => $timestamp
      ]
    );

    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    $points = $result['payload'];
    Assert::equal(147, $points['points']);
    Assert::equal("update some testing note", $points['note']);
    Assert::equal($this->user->getId(), $points['authorId']);
    Assert::equal($shadowPoints->getAwardee()->getId(), $points['awardeeId']);
    Assert::equal($timestamp, $points['awardedAt']);
  }

  public function testRemovePoints()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);
    $pointsId = current($this->presenter->shadowAssignmentPointsRepository->findAll())->getId();

    $request = new Nette\Application\Request('V1:ShadowAssignments', 'DELETE',
      ['action' => 'removePoints', 'pointsId' => $pointsId]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal("OK", $result['payload']);
    Assert::exception(function () use ($pointsId) {
      $this->presenter->shadowAssignmentPointsRepository->findOrThrow($pointsId);
    }, NotFoundException::class);
  }
}

$testCase = new TestShadowAssignmentsPresenter();
$testCase->run();
