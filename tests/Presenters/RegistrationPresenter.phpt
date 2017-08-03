<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\BadRequestException;
use App\Model\Repository\Users;
use App\V1Module\Presenters\RegistrationPresenter;
use Tester\Assert;
use App\Helpers\ExternalLogin\UserData;

/**
 * @httpCode any
 */
class TestRegistrationPresenter extends Tester\TestCase
{
  /** @var RegistrationPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

  /** @var Nette\Security\User */
  private $user;

  /** @var string */
  private $presenterPath = "V1:Registration";

  /** @var App\Model\Repository\Instances */
  protected $instances;

  /** @var App\Model\Repository\Logins */
  protected $logins;

  /** @var App\Model\Repository\ExternalLogins */
  protected $externalLogins;

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
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);
    $this->presenter = PresenterTestHelper::createPresenter($this->container, RegistrationPresenter::class);
  }

  protected function tearDown()
  {
    Mockery::close();

    if ($this->user->isLoggedIn()) {
      $this->user->logout(TRUE);
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
    $username = "user@domain.tld";
    $firstname = "firstnameExt";
    $lastname = "lastnameExt";
    $password = "passwordExt";
    $degreesBeforeName = "degreesBeforeName";
    $degreesAfterName = "degreesAfterName";
    $instances = $this->instances->findAll();
    $instanceId = array_pop($instances)->getId();
    $serviceId = "serviceId";

    // setup mocking authService
    $mockExternalLoginService = Mockery::mock(\App\Helpers\ExternalLogin\IExternalLoginService::class);
    $mockExternalLoginService->shouldReceive("getServiceId")->withAnyArgs()->andReturn($serviceId);

    $mockAuthService = Mockery::mock(\App\Helpers\ExternalLogin\AuthService::class);
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
    $createdUser = $this->externalLogins->getUser($serviceId, $userId);
    Assert::same($user, $createdUser);
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

}

$testCase = new TestRegistrationPresenter();
$testCase->run();