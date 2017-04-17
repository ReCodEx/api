<?php

use App\Exceptions\BadRequestException;
use App\Helpers\ExternalLogin\ExternalServiceAuthenticator;
use App\Helpers\ExternalLogin\IExternalLoginService;
use Tester\Assert;

include "../bootstrap.php";

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

        $logins = Mockery::mock(\App\Model\Repository\ExternalLogins::class);
        $authenticator = new ExternalServiceAuthenticator($logins, $serviceA);
        Assert::throws(function () use ($authenticator) {
            $authenticator->findService("x");
        }, BadRequestException::class, "Bad Request - Authentication service 'x/default' is not supported.");
    }

    public function testFindById() {
        $serviceA = Mockery::mock(IExternalLoginService::class);
        $serviceA->shouldReceive("getServiceId")->andReturn("x");
        $serviceA->shouldReceive("getType")->andReturn("default");

        $logins = Mockery::mock(\App\Model\Repository\ExternalLogins::class);
        $authenticator = new ExternalServiceAuthenticator($logins, $serviceA);
        Assert::equal($serviceA, $authenticator->findService("x"));
    }

    public function testFindByAndType() {
        $serviceA = Mockery::mock(IExternalLoginService::class);
        $serviceA->shouldReceive("getServiceId")->andReturn("x");
        $serviceA->shouldReceive("getType")->andReturn("y");

        $logins = Mockery::mock(\App\Model\Repository\ExternalLogins::class);
        $authenticator = new ExternalServiceAuthenticator($logins, $serviceA);
        Assert::equal($serviceA, $authenticator->findService("x", "y"));
    }

}

$case = new ExternalServiceAuthenticatorTestCase();
$case->run();
