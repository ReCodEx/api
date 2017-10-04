<?php

use App\Exceptions\BadRequestException;
use App\Exceptions\WrongCredentialsException;
use App\Helpers\ExternalLogin\ExternalServiceAuthenticator;
use App\Helpers\ExternalLogin\IExternalLoginService;
use App\Helpers\ExternalLogin\UserData;
use App\Model\Entity\Instance;
use App\Model\Entity\Role;
use App\Model\Entity\User;
use App\Model\Repository\ExternalLogins;
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

        $logins = Mockery::mock(ExternalLogins::class);
        $users = Mockery::mock(Users::class);
        $authenticator = new ExternalServiceAuthenticator($logins, $users, $serviceA);
        Assert::throws(function () use ($authenticator) {
            $authenticator->findService("x");
        }, BadRequestException::class, "Bad Request - Authentication service 'x/default' is not supported.");
    }

    public function testFindById() {
        $serviceA = Mockery::mock(IExternalLoginService::class);
        $serviceA->shouldReceive("getServiceId")->andReturn("x");
        $serviceA->shouldReceive("getType")->andReturn("default");

        $logins = Mockery::mock(ExternalLogins::class);
        $users = Mockery::mock(Users::class);
        $authenticator = new ExternalServiceAuthenticator($logins, $users, $serviceA);
        Assert::equal($serviceA, $authenticator->findService("x"));
    }

    public function testFindByAndType() {
        $serviceA = Mockery::mock(IExternalLoginService::class);
        $serviceA->shouldReceive("getServiceId")->andReturn("x");
        $serviceA->shouldReceive("getType")->andReturn("y");

        $logins = Mockery::mock(ExternalLogins::class);
        $users = Mockery::mock(Users::class);
        $authenticator = new ExternalServiceAuthenticator($logins, $users, $serviceA);
        Assert::equal($serviceA, $authenticator->findService("x", "y"));
    }

    public function testMissingUserData() {
        $serviceA = Mockery::mock(IExternalLoginService::class);
        $serviceA->shouldReceive("getUser")->andReturn(NULL);

        $logins = Mockery::mock(ExternalLogins::class);
        $users = Mockery::mock(Users::class);
        $authenticator = new ExternalServiceAuthenticator($logins, $users, $serviceA);
        Assert::throws(function () use ($authenticator, $serviceA) {
            $authenticator->authenticate($serviceA, []);
        }, WrongCredentialsException::class, "Authentication failed.");
    }

    public function testMissingUser() {
        $userData = new UserData("123", "a@b.cd", "A", "B", "", "");

        $serviceA = Mockery::mock(IExternalLoginService::class);
        $serviceA->shouldReceive("getUser")->with([ "a" => "b" ])->andReturn($userData);
        $serviceA->shouldReceive("getServiceId")->andReturn("x");

        $logins = Mockery::mock(ExternalLogins::class);
        $logins->shouldReceive("getUser")->with("x", "123")->once()->andReturn(NULL);

        $users = Mockery::mock(Users::class);
        $users->shouldReceive("getByEmail")->andReturn(NULL);

        $authenticator = new ExternalServiceAuthenticator($logins, $users, $serviceA);
        Assert::throws(function () use ($authenticator, $serviceA) {
            $authenticator->authenticate($serviceA, [ "a" => "b" ]);
        }, WrongCredentialsException::class, "Cannot authenticate this user through x.");
    }

    public function testFindUser() {
        $userData = new UserData("123", "a@b.cd", "A", "B", "", "");

        $serviceA = Mockery::mock(IExternalLoginService::class);
        $serviceA->shouldReceive("getUser")->with([ "a" => "b" ])->andReturn($userData);
        $serviceA->shouldReceive("getServiceId")->andReturn("x");

        $role = Mockery::mock(Role::class);
        $instance = Mockery::mock(Instance::class);
        $instance->shouldReceive("addMember");
        $user = new User("a@b.cd", "A", "B", "", "", $role, $instance, FALSE);

        $logins = Mockery::mock(ExternalLogins::class);
        $logins->shouldReceive("getUser")->with("x", "123")->once()->andReturn($user);

        $users = Mockery::mock(Users::class);
        $authenticator = new ExternalServiceAuthenticator($logins, $users, $serviceA);

        Assert::equal($user, $authenticator->authenticate($serviceA, [ "a" => "b" ]));
    }

  public function testTryConnectFailed() {
    $userData = new UserData("123", "a@b.cd", "A", "B", "", "");

    $serviceA = Mockery::mock(IExternalLoginService::class);
    $serviceA->shouldReceive("getUser")->with([ "a" => "b" ])->andReturn($userData);
    $serviceA->shouldReceive("getServiceId")->andReturn("x");

    $role = Mockery::mock(Role::class);
    $instance = Mockery::mock(Instance::class);
    $instance->shouldReceive("addMember");
    $user = new User("a@b.cd", "A", "B", "", "", $role, $instance, FALSE);
    $user->setVerified(true); // user has to be verified

    $logins = Mockery::mock(ExternalLogins::class);
    $logins->shouldReceive("getUser")->with("x", "123")->once()->andReturn(null);

    $users = Mockery::mock(Users::class);
    $users->shouldReceive("getByEmail")->with("a@b.cd")->andReturn(null)->once();

    Assert::exception(function () use ($logins, $users, $serviceA) {
      $authenticator = new ExternalServiceAuthenticator($logins, $users, $serviceA);
      $authenticator->authenticate($serviceA, [ "a" => "b" ]);
    }, WrongCredentialsException::class);
  }

  public function testTryConnectCorrect() {
    $userData = new UserData("123", "a@b.cd", "A", "B", "", "");

    $serviceA = Mockery::mock(IExternalLoginService::class);
    $serviceA->shouldReceive("getUser")->with([ "a" => "b" ])->andReturn($userData);
    $serviceA->shouldReceive("getServiceId")->andReturn("x");

    $role = Mockery::mock(Role::class);
    $instance = Mockery::mock(Instance::class);
    $instance->shouldReceive("addMember");
    $user = new User("a@b.cd", "A", "B", "", "", $role, $instance, FALSE);
    $user->setVerified(true); // user has to be verified

    $logins = Mockery::mock(ExternalLogins::class);
    $logins->shouldReceive("getUser")->with("x", "123")->once()->andReturn(null);
    $logins->shouldReceive("connect")->with($serviceA, $user, $userData->getId())->andReturn(true)->once();

    $users = Mockery::mock(Users::class);
    $users->shouldReceive("getByEmail")->with("a@b.cd")->andReturn($user)->once();

    $authenticator = new ExternalServiceAuthenticator($logins, $users, $serviceA);
    Assert::equal($user, $authenticator->authenticate($serviceA, [ "a" => "b" ]));
  }

}

$case = new ExternalServiceAuthenticatorTestCase();
$case->run();
