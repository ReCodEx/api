<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\BadRequestException;
use App\Exceptions\WrongCredentialsException;
use App\Helpers\EmailVerificationHelper;
use App\Model\Entity\Instance;
use App\Model\Entity\User;
use App\Model\Repository\Users;
use App\V1Module\Presenters\RegistrationPresenter;
use Tester\Assert;
use App\Helpers\ExternalLogin\UserData;

/**
 * @httpCode any
 * @testCase
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

  /** @var Users */
  private $users;

  public function __construct()
  {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
    $this->instances = $container->getByType(\App\Model\Repository\Instances::class);
    $this->logins = $container->getByType(\App\Model\Repository\Logins::class);
    $this->users = $container->getByType(\App\Model\Repository\Users::class);
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
          'passwordConfirm' => $password,
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
    Assert::type(User::class, $user);
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
        'passwordConfirm' => $password,
        'instanceId' => $instanceId,
        'degreesBeforeName' => $degreesBeforeName,
        'degreesAfterName' => $degreesAfterName
      ]
    );

    Assert::throws(function () use ($request) {
        $this->presenter->run($request);
    }, BadRequestException::class, "Bad Request - Instance '$instanceId' does not exist.");
  }

  public function testCreateAccountBadConfirmationPassword()
  {
    $email = "email@email.email";
    $firstName = "firstName";
    $lastName = "lastName";
    $password = "password";
    $passwordConfirm = "passwordConfirm";
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
        'passwordConfirm' => $passwordConfirm,
        'instanceId' => $instanceId,
        'degreesBeforeName' => $degreesBeforeName,
        'degreesAfterName' => $degreesAfterName
      ]
    );

    Assert::throws(function () use ($request) {
      $this->presenter->run($request);
    }, WrongCredentialsException::class);
  }

  public function testCreateAccountExistingUser()
  {
    $email = PresenterTestHelper::ADMIN_LOGIN;
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
        'passwordConfirm' => $password,
        'instanceId' => $instanceId,
        'degreesBeforeName' => $degreesBeforeName,
        'degreesAfterName' => $degreesAfterName
      ]
    );

    Assert::throws(function () use ($request) {
        $this->presenter->run($request);
    }, BadRequestException::class, "Bad Request - This email address is already taken.");
  }

  public function testCreateAccountExt()
  {
    $username = "user@domain.tld";
    $firstname = "firstnameExt";
    $lastname = "lastnameExt";
    $degreesBeforeName = "degreesBeforeName";
    $degreesAfterName = "degreesAfterName";
    $instance = current($this->instances->findAll());
    $instanceId = $instance->getId();
    $serviceId = "serviceId";

    $user = new User($username, $firstname, $lastname, $degreesBeforeName,
      $degreesAfterName, "", $instance, FALSE);

    // setup mocking authService
    $mockExternalLoginService = Mockery::mock(\App\Helpers\ExternalLogin\IExternalLoginService::class);
    $mockExternalLoginService->shouldReceive("getServiceId")->withAnyArgs()->andReturn($serviceId);

    $mockAuthService = Mockery::mock(\App\Helpers\ExternalLogin\ExternalServiceAuthenticator::class);
    $mockAuthService->shouldReceive("findService")
      ->with($serviceId, NULL)->andReturn($mockExternalLoginService)->once();
    $mockAuthService->shouldReceive("register")->with($mockExternalLoginService, $instance, Mockery::any())
      ->andReturn($user)->once();

    // set mocks to presenter
    $this->presenter->externalServiceAuthenticator = $mockAuthService;

    // mock verification email helper
    $mockVerificationEmail = Mockery::mock(EmailVerificationHelper::class);
    $mockVerificationEmail->shouldReceive("process")->with($user)->once();
    $this->presenter->emailVerificationHelper = $mockVerificationEmail;

    $request = new Nette\Application\Request($this->presenterPath, 'POST',
      ['action' => 'createAccountExt'],
      [
        'username' => $username,
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
    Assert::type(User::class, $user);
    Assert::equal($username, $user->getEmail());
    Assert::equal($firstname, $user->getFirstName());
    Assert::equal($lastname, $user->getLastName());
    Assert::equal($instanceId, $user->getInstance()->getId());
    Assert::equal($degreesBeforeName, $user->getDegreesBeforeName());
    Assert::equal($degreesAfterName, $user->getDegreesAfterName());
  }

  public function testLinkExternalAccount()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);
    $existingUser = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);
    $existingUser->setVerified(true);

    $userId = "userIdExt";
    $username = "user@domain.tld";
    $password = "passwordExt";
    $instance = current($this->instances->findAll());
    $instanceId = $instance->getId();
    $serviceId = "serviceId";

    // setup mocking authService
    $mockExternalLoginService = Mockery::mock(\App\Helpers\ExternalLogin\IExternalLoginService::class);
    $mockExternalLoginService->shouldReceive("getServiceId")->withAnyArgs()->andReturn($serviceId);

    $mockAuthService = Mockery::mock(\App\Helpers\ExternalLogin\ExternalServiceAuthenticator::class);
    $mockAuthService->shouldReceive("findService")
      ->with($serviceId, NULL)->andReturn($mockExternalLoginService)->once();
    $mockAuthService->shouldReceive("register")->with($mockExternalLoginService, $instance, Mockery::any())
      ->andReturn($existingUser)->once();

    // set mocks to presenter
    $this->presenter->externalServiceAuthenticator = $mockAuthService;

    // mock verification email helper
    $mockVerificationEmail = Mockery::mock(EmailVerificationHelper::class);
    $mockVerificationEmail->shouldReceive("process")->withAnyArgs()->never();
    $this->presenter->emailVerificationHelper = $mockVerificationEmail;

    $request = new Nette\Application\Request($this->presenterPath, 'POST',
      ['action' => 'createAccountExt'],
      [
        'username' => $username,
        'password' => $password,
        'instanceId' => $instanceId,
        'serviceId' => $serviceId,
        'userId' => $existingUser->getId()
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
    Assert::type(User::class, $user);
    Assert::same($existingUser, $user);
    Assert::equal($existingUser->getEmail(), $user->getEmail());
  }

  public function testValidateRegistrationData()
  {
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
