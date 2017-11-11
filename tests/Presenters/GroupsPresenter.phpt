<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Model\Entity\Group;
use App\Model\Entity\Instance;
use App\V1Module\Presenters\GroupsPresenter;
use Tester\Assert;


/**
 * @testCase
 */
class TestGroupsPresenter extends Tester\TestCase
{
  private $userLogin = "user2@example.com";
  private $userPassword = "password2";

  private $adminLogin = "admin@admin.com";
  private $adminPassword = "admin";

  /** @var GroupsPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

  /** @var  Nette\DI\Container */
  protected $container;

  /** @var Nette\Security\User */
  private $user;

  /** @var \App\Security\AccessManager */
  private $accessManager;

  public function __construct()
  {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::getEntityManager($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
    $this->accessManager = $container->getByType(\App\Security\AccessManager::class);
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);
    $this->presenter = PresenterTestHelper::createPresenter($this->container, GroupsPresenter::class);
  }

  protected function tearDown()
  {
    if ($this->user->isLoggedIn()) {
      $this->user->logout(TRUE);
    }
  }

  public function testUserCannotListAllGroups()
  {
    $token = PresenterTestHelper::login($this->container, $this->userLogin, $this->userPassword);

    /** @var \Nette\Application\Responses\JsonResponse $response */
    Assert::exception(function () {
      $request = new Nette\Application\Request('V1:Groups', 'GET', ['action' => 'default']);
      $this->presenter->run($request);
    }, App\Exceptions\ForbiddenRequestException::class);
  }

  public function testAdminCanListAllGroups()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);

    $request = new Nette\Application\Request('V1:Groups', 'GET', ['action' => 'default']);

    /** @var \Nette\Application\Responses\JsonResponse $response */
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result["code"]);
    Assert::equal(4, count($result["payload"]));
  }

  public function testUserCannotJoinPrivateGroup()
  {
    $token = PresenterTestHelper::login($this->container, $this->userLogin, $this->userPassword);

    $user = $this->accessManager->getUser($this->accessManager->decodeToken($token));
    $group = $user->instance->getGroups()->filter(
      function (Group $group) { return !$group->isPublic; }
    )->first();

    $request = new Nette\Application\Request('V1:Groups', 'POST', [
      'action' => 'addStudent',
      'id' => $group->id,
      'userId' => $user->id
    ]);

    /** @var \Nette\Application\Responses\JsonResponse $response */
    Assert::exception(function () use ($request) {
      $this->presenter->run($request);
    }, App\Exceptions\ForbiddenRequestException::class);
  }

  public function testUserCanJoinPublicGroup()
  {
    $token = PresenterTestHelper::login($this->container, $this->userLogin);

    $user = $this->accessManager->getUser($this->accessManager->decodeToken($token));

    /** @var Group $group */
    $group = $user->getInstance()->getGroups()->filter(
      function (Group $group) use ($user) { return $group->isPublic() && !$group->isMemberOf($user); }
    )->first();

    $request = new Nette\Application\Request('V1:Groups', 'POST', [
      'action' => 'addStudent',
      'id' => $group->getId(),
      'userId' => $user->getId()
    ]);

    /** @var \Nette\Application\Responses\JsonResponse $response */
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result["code"]);
  }

  public function testRemoveStudent()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $group = $this->presenter->groups->findAll()[0];
    $user = $this->presenter->users->getByEmail($this->userLogin);
    $user->makeStudentOf($group); // ! necessary
    $this->presenter->users->flush();

    // initial checks
    Assert::equal(TRUE, $group->isStudentOf($user));

    $request = new Nette\Application\Request('V1:Groups', 'DELETE', [
      'action' => 'removeStudent',
      'id' => $group->id,
      'userId' => $user->id
    ]);

    /** @var \Nette\Application\Responses\JsonResponse $response */
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    $payload = $result["payload"];
    Assert::equal(200, $result["code"]);

    Assert::equal(FALSE, $payload->isStudentOf($user));
  }

  public function testAddGroup()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin);

    /** @var Instance $instance */
    $instance = $this->presenter->instances->findAll()[0];
    $allGroupsCount = count($this->presenter->groups->findAll());

    $request = new Nette\Application\Request('V1:Groups',
      'POST',
      ['action' => 'addGroup'],
      [
        'localizedTexts' => [[
          'locale' => 'en',
          'name' => 'new name',
          'description' => 'some neaty description'
        ]],
        'instanceId' => $instance->getId(),
        'externalId' => 'external identification of exercise',
        'parentGroupId' => NULL,
        'publicStats' => TRUE,
        'isPublic' => TRUE
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    /** @var Group $payload */
    $payload = $result['payload'];

    Assert::count(1, $payload->getLocalizedTexts());
    $localizedGroup = $payload->getLocalizedTextByLocale("en");
    Assert::notSame(null, $localizedGroup);

    Assert::equal(200, $result['code']);
    Assert::count($allGroupsCount + 1, $this->presenter->groups->findAll());
    Assert::equal('new name', $localizedGroup->getName());
    Assert::equal('some neaty description', $localizedGroup->getDescription());
    Assert::equal($instance->getId(), $payload->getInstance()->getId());
    Assert::equal('external identification of exercise', $payload->getExternalId());
    Assert::equal($instance->getRootGroup(), $payload->getParentGroup());
    Assert::equal(TRUE, $payload->statsArePublic());
    Assert::equal(TRUE, $payload->isPublic());
  }

  public function testValidateAddGroupData()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin);

    $instance = $this->presenter->instances->findAll()[0];

    $request = new Nette\Application\Request('V1:Groups',
      'POST',
      ['action' => 'validateAddGroupData'],
      [
        'name' => 'new name',
        'locale' => 'en',
        'instanceId' => $instance->getId(),
        'parentGroupId' => NULL,
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    $payload = $result['payload'];
    Assert::equal(200, $result['code']);
    Assert::equal(TRUE, $payload['groupNameIsFree']);
  }

  public function testValidateAddGroupDataNameExists()
  {
    PresenterTestHelper::login($this->container, $this->adminLogin);

    /** @var Instance $instance */
    $instance = null;

    /** @var Group $group */
    $group = null;

    foreach ($this->presenter->instances->findAll() as $instance) {
      /** @var Group $candidate */
      foreach ($instance->getGroups() as $candidate) {
        if ($candidate->getParentGroup() === $instance->getRootGroup()) {
          $group = $candidate;
          break;
        }
      }
    }

    Assert::notSame(null, $instance);
    Assert::notSame(null, $group);

    $request = new Nette\Application\Request('V1:Groups',
      'POST',
      ['action' => 'validateAddGroupData'],
      [
        'name' => $group->getLocalizedTextByLocale("en")->getName(),
        'locale' => 'en',
        'instanceId' => $instance->getId(),
        'parentGroupId' => NULL,
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    $payload = $result['payload'];
    Assert::equal(200, $result['code']);
    Assert::equal(false, $payload['groupNameIsFree']);
  }

  public function testUpdateGroup()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin);

    $allGroups = $this->presenter->groups->findAll();
    $group = array_pop($allGroups);

    $request = new Nette\Application\Request('V1:Groups',
      'POST',
      ['action' => 'updateGroup', 'id' => $group->getId()],
      [
        'localizedTexts' => [[
          'locale' => 'en',
          'name' => 'new name',
          'description' => 'some neaty description',
        ]],
        'externalId' => 'external identification of exercise',
        'publicStats' => TRUE,
        'isPublic' => TRUE,
        'threshold' => 80
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    /** @var Group $payload */
    $payload = $result['payload'];
    Assert::equal(200, $result['code']);

    Assert::equal($group->getId(), $payload->getId());
    Assert::count(1, $payload->getLocalizedTexts());
    $localizedGroup = $payload->getLocalizedTextByLocale("en");
    Assert::notSame(null, $localizedGroup);
    Assert::equal('new name', $localizedGroup->getName());
    Assert::equal('some neaty description', $localizedGroup->getDescription());
    Assert::equal('external identification of exercise', $payload->getExternalId());
    Assert::equal(TRUE, $payload->statsArePublic());
    Assert::equal(TRUE, $payload->isPublic());
    Assert::equal(0.8, $payload->getThreshold());
  }

  public function testRemoveGroup()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin);

    $instance = $this->presenter->instances->findAll()[0];
    $groups = $this->presenter->groups->findAll();
    $allGroupsCount = count($groups);
    $group = array_pop($groups);

    $request = new Nette\Application\Request('V1:Groups',
      'DELETE',
      ['action' => 'removeGroup', 'id' => $group->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    $payload = $result['payload'];
    Assert::equal(200, $result['code']);
    Assert::equal("OK", $payload);
    Assert::count($allGroupsCount - 1, $this->presenter->groups->findAll());
  }

  public function testDetail()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin);

    $groups = $this->presenter->groups->findAll();
    $group = array_pop($groups);

    $request = new Nette\Application\Request('V1:Groups',
      'GET',
      ['action' => 'detail', 'id' => $group->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    $payload = $result['payload'];
    Assert::equal(200, $result['code']);
    Assert::equal($group->getId(), $payload->id);
  }

  public function testPublicDetail()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin);

    $groups = $this->presenter->groups->findAll();
    $group = array_pop($groups);

    $request = new Nette\Application\Request('V1:Groups',
      'GET',
      ['action' => 'publicDetail', 'id' => $group->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    $payload = $result['payload'];
    Assert::equal(200, $result['code']);
    Assert::equal($group->getId(), $payload['id']);
    Assert::true($payload['canView']);
  }

  public function testSubgroups()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin);

    $groups = $this->presenter->groups->findAll();
    $group = array_pop($groups);

    $request = new Nette\Application\Request('V1:Groups',
      'GET',
      ['action' => 'subgroups', 'id' => $group->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    $payload = $result['payload'];
    Assert::equal(200, $result['code']);
    Assert::equal($group->getAllSubgroups(), $payload); // admin can access everything
  }

  public function testMembers()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin);

    $groups = $this->presenter->groups->findAll();
    $group = array_pop($groups);

    $request = new Nette\Application\Request('V1:Groups',
      'GET',
      ['action' => 'members', 'id' => $group->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    $payload = $result['payload'];
    Assert::equal(200, $result['code']);

    Assert::equal($group->getSupervisors()->getValues(), $payload["supervisors"]);
    Assert::equal($group->getStudents()->getValues(), $payload["students"]);
  }

  public function testSupervisors()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin);

    $groups = $this->presenter->groups->findAll();
    $group = array_pop($groups);

    $request = new Nette\Application\Request('V1:Groups',
      'GET',
      ['action' => 'supervisors', 'id' => $group->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    $payload = $result['payload'];
    Assert::equal(200, $result['code']);

    Assert::equal($group->getSupervisors()->getValues(), $payload);
  }

  public function testStudents()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin);

    $groups = $this->presenter->groups->findAll();
    $group = array_pop($groups);

    $request = new Nette\Application\Request('V1:Groups',
      'GET',
      ['action' => 'students', 'id' => $group->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    $payload = $result['payload'];
    Assert::equal(200, $result['code']);

    Assert::equal($group->getStudents()->getValues(), $payload);
  }

  public function testAssignments()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin);

    $groups = $this->presenter->groups->findAll();
    $group = array_pop($groups);

    $request = new Nette\Application\Request('V1:Groups',
      'GET',
      ['action' => 'assignments', 'id' => $group->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    $payload = $result['payload'];
    Assert::equal(200, $result['code']);

    Assert::equal($group->getAssignments()->toArray(), $payload); // admin can access everything
  }

  public function testExercises()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin);

    $groups = $this->presenter->groups->findAll();
    $group = array_pop($groups);

    $request = new Nette\Application\Request('V1:Groups',
      'GET',
      ['action' => 'exercises', 'id' => $group->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    $payload = $result['payload'];
    Assert::equal(200, $result['code']);

    Assert::equal($group->getExercises()->toArray(), $payload); // admin can access everything
  }

  public function testAddSupervisor()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $group = $this->presenter->groups->findAll()[0];
    $user = $this->presenter->users->getByEmail($this->userLogin);

    // initial checks
    Assert::equal(FALSE, $group->isSupervisorOf($user));

    $request = new Nette\Application\Request('V1:Groups', 'POST', [
      'action' => 'addSupervisor',
      'id' => $group->id,
      'userId' => $user->id
    ]);

    /** @var \Nette\Application\Responses\JsonResponse $response */
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    $payload = $result["payload"];
    Assert::equal(200, $result["code"]);

    Assert::equal(TRUE, $payload->isSupervisorOf($user));
  }

  public function testRemoveSupervisor()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $group = $this->presenter->groups->findAll()[0];
    $user = $this->presenter->users->getByEmail($this->userLogin);
    $user->makeSupervisorOf($group); // ! necessary
    $this->presenter->users->flush();

    // initial checks
    Assert::equal(TRUE, $group->isSupervisorOf($user));

    $request = new Nette\Application\Request('V1:Groups', 'DELETE', [
      'action' => 'removeSupervisor',
      'id' => $group->id,
      'userId' => $user->id
    ]);

    /** @var \Nette\Application\Responses\JsonResponse $response */
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    $payload = $result["payload"];
    Assert::equal(200, $result["code"]);

    Assert::equal(FALSE, $payload->isSupervisorOf($user));
  }

  public function testGetAdmins()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin);

    $groups = $this->presenter->groups->findAll();
    $group = array_pop($groups);

    $request = new Nette\Application\Request('V1:Groups',
      'GET',
      ['action' => 'admins', 'id' => $group->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    $payload = $result['payload'];
    Assert::equal(200, $result['code']);

    Assert::equal($group->getAdminsIds(), $payload);
  }

  public function testAddAdmin()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    $group = $this->presenter->groups->findAll()[0];
    $user = $this->presenter->users->getByEmail($this->userLogin);

    // initial checks
    Assert::equal(FALSE, $group->isAdminOf($user));

    // initial setup
    $user->makeSupervisorOf($group);
    $this->presenter->groups->flush();

    $request = new Nette\Application\Request('V1:Groups',
      'POST',
      ['action' => 'addAdmin', 'id' => $group->id],
      ['userId' => $user->id]
    );

    /** @var \Nette\Application\Responses\JsonResponse $response */
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    $payload = $result["payload"];
    Assert::equal(200, $result["code"]);

    Assert::equal(TRUE, $payload->isAdminOf($user));
  }

  public function testRemoveAdmin()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    $group = $this->presenter->groups->findAll()[1];
    $user = $group->getPrimaryAdmins()->first();

    $request = new Nette\Application\Request('V1:Groups',
      'DELETE',
      ['action' => 'removeAdmin', 'id' => $group->id, 'userId' => $user->getId()]
    );

    /** @var \Nette\Application\Responses\JsonResponse $response */
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    $payload = $result["payload"];
    Assert::equal(200, $result["code"]);

    Assert::equal(FALSE, $group->isAdminOf($user));
  }

}

$testCase = new TestGroupsPresenter();
$testCase->run();
