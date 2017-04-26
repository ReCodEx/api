<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\BadRequestException;
use App\Model\Repository\Users;
use App\V1Module\Presenters\UsersPresenter;
use Tester\Assert;
use App\Helpers\ExternalLogin\UserData;

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

  /** @var App\Model\Repository\Instances */
  protected $instances;

  /** @var App\Model\Repository\Logins */
  protected $logins;

  /** @var App\Model\Repository\ExternalLogins */
  protected $externalLogins;

  /** @var App\Model\Repository\Users */
  protected $users;

  /** @var App\Model\Repository\GroupMemberships */
  protected $groupMemberships;

  /** @var  Nette\DI\Container */
  protected $container;

  public function __construct()
  {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
    $this->instances = $container->getByType(\App\Model\Repository\Instances::class);
    $this->logins = $container->getByType(\App\Model\Repository\Logins::class);
    $this->externalLogins = $container->getByType(\App\Model\Repository\ExternalLogins::class);
    $this->users = $container->getByType(Users::class);
    $this->groupMemberships = $container->getByType(\App\Model\Repository\GroupMemberships::class);
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

  public function testCreateAccount()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $email = "email@email.email";
    $firstName = "firstName";
    $lastName = "lastName";
    $password = "password";
    $instances = $this->instances->findAll();
    $instanceId = array_pop($instances)->getId();
    $degreesBeforeName = "degreesBeforeName";
    $degreesAfterName = "degreesAfterName";

    $request = new Nette\Application\Request($this->presenterPath, 'POST',
      ['action' => 'createAccount'],
      [
          'email' => $email,
          'firstName' => $firstName,
          'lastName' => $lastName,
          'password' => $password,
          'instanceId' => $instanceId,
          'degreesBeforeName' => $degreesBeforeName,
          'degreesAfterName' => $degreesAfterName
        ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(201, $result['code']);
    Assert::equal(2, count($result['payload']));
    Assert::true(array_key_exists("accessToken", $result["payload"]));
    Assert::true(array_key_exists("user", $result["payload"]));

    // check created user
    $user = $result["payload"]["user"];
    Assert::type(\App\Model\Entity\User::class, $user);
    Assert::equal($email, $user->getEmail());
    Assert::equal($firstName, $user->getFirstName());
    Assert::equal($lastName, $user->getLastName());
    Assert::equal($instanceId, $user->getInstance()->getId());
    Assert::equal($degreesBeforeName, $user->getDegreesBeforeName());
    Assert::equal($degreesAfterName, $user->getDegreesAfterName());

    // check created login
    $login = $this->logins->findByUserId($user->getId());
    Assert::same($user, $login->getUser());
    Assert::true($login->passwordsMatch($password));
  }

  public function testCreateAccountIcorrectInstance()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    $email = "email@email.email";
    $firstName = "firstName";
    $lastName = "lastName";
    $password = "password";
    $instanceId = "bla bla bla random string";
    $degreesBeforeName = "degreesBeforeName";
    $degreesAfterName = "degreesAfterName";

    $request = new Nette\Application\Request($this->presenterPath, 'POST',
      ['action' => 'createAccount'],
      [
        'email' => $email,
        'firstName' => $firstName,
        'lastName' => $lastName,
        'password' => $password,
        'instanceId' => $instanceId,
        'degreesBeforeName' => $degreesBeforeName,
        'degreesAfterName' => $degreesAfterName
      ]
    );

    Assert::throws(function () use ($request) {
        $this->presenter->run($request);
    }, BadRequestException::class, "Bad Request - Instance '$instanceId' does not exist.");
  }

  public function testCreateAccountMissingFields()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    $email = "email@email.email";
    $firstName = "firstName";
    $lastName = "lastName";
    $password = "password";
    $instanceId = "bla bla bla random string";
    $degreesBeforeName = "degreesBeforeName";
    $degreesAfterName = "degreesAfterName";

    $request = new Nette\Application\Request($this->presenterPath, 'POST',
      ['action' => 'createAccount'],
      [
        'email' => $email,
        'firstName' => $firstName,
        'lastName' => $lastName,
        'password' => $password,
        'instanceId' => $instanceId,
        'degreesBeforeName' => $degreesBeforeName,
        'degreesAfterName' => $degreesAfterName
      ]
    );

    // mock users model
    $mockUsers = Mockery::mock(Users::class);
    $mockUsers->shouldReceive("getByEmail")
        ->with($email)
        ->once()
        ->andReturn(TRUE);

    $this->presenter->users = $mockUsers;

    Assert::throws(function () use ($request) {
        $this->presenter->run($request);
    }, BadRequestException::class, "Bad Request - This email address is already taken.");
  }

  public function testCreateAccountExt()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $userId = "userIdExt";
    $username = "usernameExt";
    $firstname = "firstnameExt";
    $lastname = "lastnameExt";
    $password = "passwordExt";
    $degreesBeforeName = "degreesBeforeName";
    $degreesAfterName = "degreesAfterName";
    $instances = $this->instances->findAll();
    $instanceId = array_pop($instances)->getId();
    $serviceId = "serviceId";

    // setup mocking authService
    $mockAuthService = Mockery::mock(\App\Helpers\ExternalLogin\AuthService::class);
    $mockExternalLoginService = Mockery::mock(\App\Helpers\ExternalLogin\IExternalLoginService::class);

    $mockAuthService->shouldReceive("findService")
      ->with($serviceId, NULL)->andReturn($mockExternalLoginService)->once();

    $mockExternalLoginService->shouldReceive("getUser")->withAnyArgs()
      ->andReturn(new UserData(
        $userId, $username, $firstname, $lastname, $degreesBeforeName, $degreesAfterName, $mockExternalLoginService
      ))
      ->once();

    // set mocks to presenter
    $this->presenter->externalServiceAuthenticator = $mockAuthService;

    $request = new Nette\Application\Request($this->presenterPath, 'POST',
      ['action' => 'createAccountExt'],
      [
        'username' => $username,
        'password' => $password,
        'instanceId' => $instanceId,
        'serviceId' => $serviceId
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(201, $result['code']);
    Assert::equal(2, count($result['payload']));
    Assert::true(array_key_exists("accessToken", $result["payload"]));
    Assert::true(array_key_exists("user", $result["payload"]));

    // check created user
    $user = $result["payload"]["user"];
    Assert::type(\App\Model\Entity\User::class, $user);
    Assert::equal($username, $user->getEmail());
    Assert::equal($firstname, $user->getFirstName());
    Assert::equal($lastname, $user->getLastName());
    Assert::equal($instanceId, $user->getInstance()->getId());
    Assert::equal($degreesBeforeName, $user->getDegreesBeforeName());
    Assert::equal($degreesAfterName, $user->getDegreesAfterName());

    // check created login
    $login = $this->externalLogins->findByExternalId($userId);
    Assert::same($user, $login->getUser());
  }

  public function testValidateRegistrationData()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $request = new Nette\Application\Request($this->presenterPath, 'POST',
      ['action' => 'validateRegistrationData'],
      [
        'email' => "totallyFreeEmail@EmailFreeTotally.freeEmailTotally",
        'password' => "totallySecurePasswordWhichIsNot123456"
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(2, $result['payload']);

    Assert::true(array_key_exists("usernameIsFree", $result["payload"]));
    Assert::true($result["payload"]["usernameIsFree"]);

    Assert::true(array_key_exists("passwordScore", $result["payload"]));
    Assert::type('int', $result["payload"]["passwordScore"]);
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
      ['action' => 'updateProfile'],
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

    $request = new Nette\Application\Request($this->presenterPath, 'POST',
      ['action' => 'updateSettings'],
      [
        'darkTheme' => $darkTheme,
        'vimMode' => $vimMode,
        'defaultLanguage' => $defaultLanguage
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

}

$testCase = new TestUsersPresenter();
$testCase->run();