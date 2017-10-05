<?php

use App\Exceptions\BadRequestException;
use App\Exceptions\WrongCredentialsException;
use App\Helpers\ExternalLogin\ExternalServiceAuthenticator;
use App\Helpers\ExternalLogin\IExternalLoginService;
use App\Helpers\ExternalLogin\UserData;
use App\Model\Entity\ExternalLogin;
use App\Model\Entity\Instance;
use App\Model\Entity\User;
use App\Model\Repository\ExternalLogins;
use App\Model\Repository\Logins;
use App\Model\Repository\Users;
use Tester\Assert;

include "../bootstrap.php";


/**
 * @testCase
 */
class ExternalServiceAuthenticatorTestCase extends Tester\TestCase {

  /** @var  Nette\DI\Container */
  protected $container;

  public function __construct() {
    global $container;
    $this->container = $container;
  }

  public function testThrowIfCannotFindService() {
    $serviceA = Mockery::mock(IExternalLoginService::class);
    $serviceA->shouldReceive("getServiceId")->andReturn("x");
    $serviceA->shouldReceive("getType")->andReturn("u");

    $externalLogins = Mockery::mock(ExternalLogins::class);
    $users = Mockery::mock(Users::class);
    $logins = Mockery::mock(Logins::class);
    $authenticator = new ExternalServiceAuthenticator($externalLogins, $users, $logins, $serviceA);
    Assert::throws(function () use ($authenticator) {
      $authenticator->findService("x");
    }, BadRequestException::class, "Bad Request - Authentication service 'x/default' is not supported.");
  }

  public function testFindById() {
    $serviceA = Mockery::mock(IExternalLoginService::class);
    $serviceA->shouldReceive("getServiceId")->andReturn("x");
    $serviceA->shouldReceive("getType")->andReturn("default");

    $externalLogins = Mockery::mock(ExternalLogins::class);
    $users = Mockery::mock(Users::class);
    $logins = Mockery::mock(Logins::class);
    $authenticator = new ExternalServiceAuthenticator($externalLogins, $users, $logins, $serviceA);
    Assert::equal($serviceA, $authenticator->findService("x"));
  }

  public function testFindByAndType() {
    $serviceA = Mockery::mock(IExternalLoginService::class);
    $serviceA->shouldReceive("getServiceId")->andReturn("x");
    $serviceA->shouldReceive("getType")->andReturn("y");

    $externalLogins = Mockery::mock(ExternalLogins::class);
    $users = Mockery::mock(Users::class);
    $logins = Mockery::mock(Logins::class);
    $authenticator = new ExternalServiceAuthenticator($externalLogins, $users, $logins, $serviceA);
    Assert::equal($serviceA, $authenticator->findService("x", "y"));
  }

  public function testAuthenticateMissingUserData() {
    $serviceA = Mockery::mock(IExternalLoginService::class);
    $serviceA->shouldReceive("getUser")->andReturn(NULL);

    $externalLogins = Mockery::mock(ExternalLogins::class);
    $users = Mockery::mock(Users::class);
    $logins = Mockery::mock(Logins::class);
    $authenticator = new ExternalServiceAuthenticator($externalLogins, $users, $logins, $serviceA);
      Assert::throws(function () use ($authenticator, $serviceA) {
        $authenticator->authenticate($serviceA, []);
      }, WrongCredentialsException::class, "Authentication failed.");
  }

  public function testAuthenticateMissingUser() {
    $userData = new UserData("123", "a@b.cd", "A", "B", "", "");

    $serviceA = Mockery::mock(IExternalLoginService::class);
    $serviceA->shouldReceive("getUser")->with([ "a" => "b" ])->andReturn($userData);
    $serviceA->shouldReceive("getServiceId")->andReturn("x");

    $externalLogins = Mockery::mock(ExternalLogins::class);
    $externalLogins->shouldReceive("getUser")->with("x", "123")->once()->andReturn(NULL);

    $users = Mockery::mock(Users::class);
    $users->shouldReceive("getByEmail")->andReturn(NULL);

    $logins = Mockery::mock(Logins::class);

    $authenticator = new ExternalServiceAuthenticator($externalLogins, $users, $logins, $serviceA);
    Assert::throws(function () use ($authenticator, $serviceA) {
      $authenticator->authenticate($serviceA, [ "a" => "b" ]);
    }, WrongCredentialsException::class, "Cannot authenticate this user through x.");
  }

  public function testAuthenticateFindUser() {
    $userData = new UserData("123", "a@b.cd", "A", "B", "", "");

    $serviceA = Mockery::mock(IExternalLoginService::class);
    $serviceA->shouldReceive("getUser")->with([ "a" => "b" ])->andReturn($userData);
    $serviceA->shouldReceive("getServiceId")->andReturn("x");

    $instance = Mockery::mock(Instance::class);
    $instance->shouldReceive("addMember");
    $user = new User("a@b.cd", "A", "B", "", "", "", $instance, FALSE);

    $externalLogins = Mockery::mock(ExternalLogins::class);
    $externalLogins->shouldReceive("getUser")->with("x", "123")->once()->andReturn($user);

    $users = Mockery::mock(Users::class);
    $logins = Mockery::mock(Logins::class);
    $authenticator = new ExternalServiceAuthenticator($externalLogins, $users, $logins, $serviceA);

    Assert::equal($user, $authenticator->authenticate($serviceA, [ "a" => "b" ]));
  }

  public function testAuthenticateTryConnectFailed() {
    $userData = new UserData("123", "a@b.cd", "A", "B", "", "");

    $serviceA = Mockery::mock(IExternalLoginService::class);
    $serviceA->shouldReceive("getUser")->with([ "a" => "b" ])->andReturn($userData);
    $serviceA->shouldReceive("getServiceId")->andReturn("x");

    $instance = Mockery::mock(Instance::class);
    $instance->shouldReceive("addMember");
    $user = new User("a@b.cd", "A", "B", "", "", "", $instance, FALSE);
    $user->setVerified(true); // user has to be verified

    $externalLogins = Mockery::mock(ExternalLogins::class);
    $externalLogins->shouldReceive("getUser")->with("x", "123")->once()->andReturn(null);

    $users = Mockery::mock(Users::class);
    $users->shouldReceive("getByEmail")->with("a@b.cd")->andReturn(null)->once();

    $logins = Mockery::mock(Logins::class);

    Assert::exception(function () use ($externalLogins, $users, $logins, $serviceA) {
      $authenticator = new ExternalServiceAuthenticator($externalLogins, $users, $logins, $serviceA);
      $authenticator->authenticate($serviceA, [ "a" => "b" ]);
    }, WrongCredentialsException::class);
  }

  public function testAuthenticateTryConnectCorrect() {
    $userData = new UserData("123", "a@b.cd", "A", "B", "", "");

    $serviceA = Mockery::mock(IExternalLoginService::class);
    $serviceA->shouldReceive("getUser")->with([ "a" => "b" ])->andReturn($userData);
    $serviceA->shouldReceive("getServiceId")->andReturn("x");

    $instance = Mockery::mock(Instance::class);
    $instance->shouldReceive("addMember");
    $user = new User("a@b.cd", "A", "B", "", "", "", $instance, FALSE);
    $user->setVerified(true); // user has to be verified

    $externalLogin = new ExternalLogin($user, "", "");
    $externalLogins = Mockery::mock(ExternalLogins::class);
    $externalLogins->shouldReceive("getUser")->with("x", "123")->once()->andReturn(null);
    $externalLogins->shouldReceive("connect")->with($serviceA, $user, $userData->getId())->andReturn($externalLogin)->once();

    $users = Mockery::mock(Users::class);
    $users->shouldReceive("getByEmail")->with("a@b.cd")->andReturn($user)->once();

    $logins = Mockery::mock(Logins::class);
    $logins->shouldReceive("clearUserPassword")->with($user)->andReturn()->once();

    $authenticator = new ExternalServiceAuthenticator($externalLogins, $users, $logins, $serviceA);
    Assert::equal($user, $authenticator->authenticate($serviceA, [ "a" => "b" ]));
  }

  public function testRegisterExistingUser() {
    $userData = new UserData("123", "a@b.cd", "A", "B", "", "");

    $serviceA = Mockery::mock(IExternalLoginService::class);
    $serviceA->shouldReceive("getUser")->with([ "a" => "b" ])->andReturn($userData);
    $serviceA->shouldReceive("getServiceId")->andReturn("x");

    $instance = Mockery::mock(Instance::class);
    $instance->shouldReceive("addMember");
    $user = new User("a@b.cd", "A", "B", "", "", "", $instance, FALSE);

    $externalLogins = Mockery::mock(ExternalLogins::class);
    $externalLogins->shouldReceive("getUser")->with("x", "123")->andReturn($user)->once();

    $users = Mockery::mock(Users::class);
    $logins = Mockery::mock(Logins::class);

    $authenticator = new ExternalServiceAuthenticator($externalLogins, $users, $logins, $serviceA);
    Assert::throws(function () use ($authenticator, $serviceA) {
      $authenticator->register($serviceA, new Instance(), [ "a" => "b" ]);
    }, WrongCredentialsException::class);
  }

  public function testRegisterUserConnectWithExisting() {
    $userData = new UserData("123", "a@b.cd", "A", "B", "", "");

    $serviceA = Mockery::mock(IExternalLoginService::class);
    $serviceA->shouldReceive("getUser")->with([ "a" => "b" ])->andReturn($userData);
    $serviceA->shouldReceive("getServiceId")->andReturn("x");

    $instance = Mockery::mock(Instance::class);
    $instance->shouldReceive("addMember");
    $user = new User("a@b.cd", "A", "B", "", "", "", $instance, FALSE);

    $externalLogin = new ExternalLogin($user, "", "");
    $externalLogins = Mockery::mock(ExternalLogins::class);
    $externalLogins->shouldReceive("getUser")->with("x", "123")->andReturn(null)->once();
    $externalLogins->shouldReceive("connect")->with($serviceA, $user, $userData->getId())->andReturn($externalLogin)->once();

    $users = Mockery::mock(Users::class);
    $users->shouldReceive("getByEmail")->with("a@b.cd")->andReturn($user)->once();

    $logins = Mockery::mock(Logins::class);
    $logins->shouldReceive("clearUserPassword")->with($user)->andReturn()->once();

    $authenticator = new ExternalServiceAuthenticator($externalLogins, $users, $logins, $serviceA);
    $newUser = $authenticator->register($serviceA, new Instance(), [ "a" => "b" ]);
    Assert::same($user, $newUser);
  }

  public function testRegisterUserCorrect() {
    $userData = new UserData("123", "a@b.cd", "A", "B", "", "");

    $serviceA = Mockery::mock(IExternalLoginService::class);
    $serviceA->shouldReceive("getUser")->with([ "a" => "b" ])->andReturn($userData);
    $serviceA->shouldReceive("getServiceId")->andReturn("x");

    $externalUser = Mockery::mock(User::class);
    $externalLogin = new ExternalLogin($externalUser, "", "");
    $externalLogins = Mockery::mock(ExternalLogins::class);
    $externalLogins->shouldReceive("getUser")->with("x", "123")->andReturn(null)->once();
    $externalLogins->shouldReceive("connect")->with($serviceA, Mockery::any(), $userData->getId())->andReturn($externalLogin)->once();

    $users = Mockery::mock(Users::class);
    $users->shouldReceive("getByEmail")->with("a@b.cd")->andReturn(null)->once();
    $users->shouldReceive("persist")->withAnyArgs()->andReturn()->once();

    $logins = Mockery::mock(Logins::class);

    $authenticator = new ExternalServiceAuthenticator($externalLogins, $users, $logins, $serviceA);
    $user = $authenticator->register($serviceA, new Instance(), [ "a" => "b" ]);
    Assert::equal("A", $user->getFirstName());
    Assert::equal("B", $user->getLastName());
    Assert::equal("a@b.cd", $user->getEmail());
  }

}

$case = new ExternalServiceAuthenticatorTestCase();
$case->run();
