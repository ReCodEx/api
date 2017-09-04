<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\ForbiddenRequestException;
use App\Model\Repository\Users;
use App\V1Module\Presenters\UsersPresenter;
use Tester\Assert;

/**
 * @httpCode any
 */
class TestUsersPresenter extends Tester\TestCase
{
  /** @var UsersPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

  /** @var Nette\Security\User */
  private $user;

  /** @var string */
  private $presenterPath = "V1:Users";

  /** @var App\Model\Repository\Users */
  protected $users;

  /** @var  Nette\DI\Container */
  protected $container;

  public function __construct()
  {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
    $this->users = $container->getByType(Users::class);
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);
    $this->presenter = PresenterTestHelper::createPresenter($this->container, UsersPresenter::class);
  }

  protected function tearDown()
  {
    Mockery::close();

    if ($this->user->isLoggedIn()) {
      $this->user->logout(TRUE);
    }
  }

  public function testGetAllUsers()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $request = new Nette\Application\Request($this->presenterPath, 'GET', ['action' => 'default']);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::true(count($result['payload']) > 0);

    $users = $result['payload'];
    foreach ($users as $user) {
      Assert::type(App\Model\Entity\User::class, $user);
    }
  }

  public function testDetail()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

    $request = new Nette\Application\Request($this->presenterPath, 'GET',
      ['action' => 'detail', 'id' => $user->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal(1, count($result["payload"]));

    Assert::type(\App\Model\Entity\User::class, $result["payload"]);
    Assert::same($user, $result["payload"]);
  }

  public function testUpdateProfile()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

    $firstName = "firstNameUpdated";
    $lastName = "lastNameUpdated";
    $degreesBeforeName = "degreesBeforeNameUpdated";
    $degreesAfterName = "degreesAfterNameUpdated";

    $request = new Nette\Application\Request($this->presenterPath, 'POST',
      ['action' => 'updateProfile', 'id' => $user->getId()],
      [
        'firstName' => $firstName,
        'lastName' => $lastName,
        'degreesBeforeName' => $degreesBeforeName,
        'degreesAfterName' => $degreesAfterName
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    $updatedUser = $result["payload"];
    Assert::type(\App\Model\Entity\User::class, $updatedUser);
    Assert::equal($firstName, $updatedUser->getFirstName());
    Assert::equal($lastName, $updatedUser->getLastName());
    Assert::equal($degreesBeforeName, $updatedUser->getDegreesBeforeName());
    Assert::equal($degreesAfterName, $updatedUser->getDegreesAfterName());

    $storedUpdatedUser = $this->users->get($user->getId());
    Assert::same($updatedUser, $storedUpdatedUser);
  }

  public function testUpdateSettings()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

    $darkTheme = FALSE;
    $vimMode = FALSE;
    $defaultLanguage = "de";
    $newAssignmentEmails = false;
    $assignmentDeadlineEmails = false;
    $submissionEvaluatedEmails = false;

    $request = new Nette\Application\Request($this->presenterPath, 'POST',
      ['action' => 'updateSettings', 'id' => $user->getId()],
      [
        'darkTheme' => $darkTheme,
        'vimMode' => $vimMode,
        'defaultLanguage' => $defaultLanguage,
        'newAssignmentEmails' => $newAssignmentEmails,
        'assignmentDeadlineEmails' => $assignmentDeadlineEmails,
        'submissionEvaluatedEmails' => $submissionEvaluatedEmails
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    $user = $result["payload"];
    Assert::type(\App\Model\Entity\User::class, $user);
    Assert::equal($darkTheme, $user->getSettings()->getDarkTheme());
    Assert::equal($vimMode, $user->getSettings()->getVimMode());
    Assert::equal($defaultLanguage, $user->getSettings()->getDefaultLanguage());
    Assert::equal($newAssignmentEmails, $user->getSettings()->getNewAssignmentEmails());
    Assert::equal($assignmentDeadlineEmails, $user->getSettings()->getAssignmentDeadlineEmails());
    Assert::equal($submissionEvaluatedEmails, $user->getSettings()->getSubmissionEvaluatedEmails());
  }

  public function testSupervisorGroups()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

    $request = new Nette\Application\Request($this->presenterPath, 'GET',
      ['action' => 'groups', 'id' => $user->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(3, $result["payload"]);

    Assert::true(array_key_exists("supervisor", $result["payload"]));
    $supervisorIn = $result["payload"]["supervisor"];
    $expectedSupervisorIn = $user->getGroupsAsSupervisor()->getValues();
    Assert::equal($expectedSupervisorIn, $supervisorIn);
  }

  public function testStudentGroups()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->users->getByEmail(PresenterTestHelper::STUDENT_GROUP_MEMBER_LOGIN);

    $request = new Nette\Application\Request($this->presenterPath, 'GET',
      ['action' => 'groups', 'id' => $user->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(3, $result["payload"]);

    Assert::true(array_key_exists("student", $result["payload"]));
    $studentIn = $result["payload"]["student"];
    $expectedStudentIn = $user->getGroupsAsStudent()->getValues();
    Assert::equal($expectedStudentIn, $studentIn);

    Assert::true(array_key_exists("stats", $result["payload"]));
    $stats = $result["payload"]["stats"];
    Assert::count(count($expectedStudentIn), $stats);

    foreach ($stats as $stat) {
      Assert::count(9, $stat);
      Assert::true(array_key_exists("id", $stat));
      Assert::true(array_key_exists("name", $stat));
      Assert::true(array_key_exists("userId", $stat));
      Assert::true(array_key_exists("groupId", $stat));
      Assert::true(array_key_exists("assignments", $stat));
      Assert::true(array_key_exists("points", $stat));
      Assert::true(array_key_exists("statuses", $stat));
      Assert::true(array_key_exists("hasLimit", $stat));
      Assert::true(array_key_exists("passesLimit", $stat));
    }
  }

  public function testInstances()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

    $request = new Nette\Application\Request($this->presenterPath, 'GET',
      ['action' => 'instances', 'id' => $user->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    $instances = $result["payload"];
    Assert::equal(1, count($instances));

    $instance = array_pop($instances);
    Assert::type(\App\Model\Entity\Instance::class, $instance);
    Assert::equal($user->getInstance()->getId(), $instance->getId());
  }

  public function testExercises()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

    $request = new Nette\Application\Request($this->presenterPath, 'GET',
      ['action' => 'exercises', 'id' => $user->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    $exercises = $result["payload"];
    Assert::equal($user->getExercises()->getValues(), $exercises);

    foreach ($exercises as $exercise) {
      Assert::type(\App\Model\Entity\Exercise::class, $exercise);
      Assert::true($exercise->isAuthor($user));
    }
  }

  public function testPublicData() {
    PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

    $request = new Nette\Application\Request($this->presenterPath, 'GET',
      ['action' => 'publicData', 'id' => $user->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    $payload = $result['payload'];
    Assert::true(array_key_exists('id', $payload));
    Assert::true(array_key_exists('fullName', $payload));
    Assert::true(array_key_exists('name', $payload));
    Assert::true(array_key_exists('avatarUrl', $payload));
    Assert::true(array_key_exists('isVerified', $payload));
  }

  public function testUnauthenticatedUserCannotViewPublicData() {
    $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

    $request = new Nette\Application\Request($this->presenterPath, 'GET',
      ['action' => 'publicData', 'id' => $user->getId()]
    );

    Assert::exception(function () use ($request) {
      $this->presenter->run($request);
    }, ForbiddenRequestException::class);
  }

  public function testDeleteUser() {
    $victim = "user2@example.com";
    PresenterTestHelper::loginDefaultAdmin($this->container);
    $user = $this->users->getByEmail($victim);

    $request = new Nette\Application\Request($this->presenterPath, 'DELETE',
      ['action' => 'delete', 'id' => $user->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::null($this->users->getByEmail($victim));
  }

}

$testCase = new TestUsersPresenter();
$testCase->run();
