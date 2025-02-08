<?php

use App\Exceptions\WrongCredentialsException;
use App\Exceptions\InvalidExternalTokenException;
use App\Helpers\ExternalLogin\ExternalServiceAuthenticator;
use App\Helpers\EmailVerificationHelper;
use App\Model\Repository\Instances;
use App\Model\Repository\ExternalLogins;
use App\Model\Repository\Logins;
use App\Model\Repository\Users;
use Firebase\JWT\JWT;
use Tester\Assert;

$container = require_once __DIR__ . "/../bootstrap.php";

/**
 * @testCase
 */
class ExternalServiceAuthenticatorTestCase extends Tester\TestCase
{
    public const AUTH_NAME = 'test-cas';
    public const AUTH_SECRET = 'superSecretStringForJWT';

    /** @var  Nette\DI\Container */
    private $container;

    /** @var ExternalServiceAuthenticator */
    private $authenticator;

    /** @var ExternalLogins */
    private $externalLogins;

    /** @var Users */
    private $users;

    /** @var Logins */
    private $logins;


    public function __construct($container)
    {
        $this->container = $container;
        $this->externalLogins = $container->getByType(ExternalLogins::class);
        $this->users = $container->getByType(Users::class);
        $this->logins = $container->getByType(Logins::class);
        $this->authenticator = new ExternalServiceAuthenticator(
            [[
                'name' => self::AUTH_NAME,
                'jwtSecret' => self::AUTH_SECRET,
                'expiration' => 60,
            ]],
            $this->externalLogins,
            $this->users,
            $this->logins,
            $container->getByType(Instances::class),
            $container->getByType(EmailVerificationHelper::class),
            $container->getByType(App\Helpers\FailureHelper::class)
        );
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
    }

    protected function tearDown()
    {
        Mockery::close();
    }

    private function getLocalUser()
    {
        foreach ($this->users->findAll() as $user) {
            if (count($user->getExternalLogins()) === 0) {
                return $user;
            }
        }
        throw new \Exception("No local user found."); // this should never happen
    }

    private function getExternalUser($externId)
    {
        $user = $this->getLocalUser();
        $this->externalLogins->connect(self::AUTH_NAME, $user, $externId);
        return $user;
    }

    private function prepareToken(
        $id,
        $email = 'notimportant@recodex.test',
        $firstName = 'John',
        $lastName = 'Smith',
        $role = null
    ) {
        $payload = [
            'iat' => time(),
            'id' => $id,
            'mail' => $email,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'role' => $role,
        ];
        return JWT::encode($payload, self::AUTH_SECRET, "HS256");
    }

    public function testHasAuthenticator()
    {
        Assert::true($this->authenticator->hasAuthenticator(self::AUTH_NAME));
        Assert::false($this->authenticator->hasAuthenticator('foo'));
    }

    public function testAuthenticate()
    {
        $user = $this->getExternalUser('foo');
        $token = $this->prepareToken('foo');
        $res = $this->authenticator->authenticate(self::AUTH_NAME, $token);
        Assert::equal($user->getId(), $res->getId());
    }

    public function testAuthenticateFail()
    {
        Assert::exception(function () {
            $token = $this->prepareToken('bar');
            $res = $this->authenticator->authenticate(
                self::AUTH_NAME,
                $token,
                'ae497c87-ce9e-4c9d-a86c-7d1a8ace9898' // non-existing ID
            );
        }, WrongCredentialsException::class);
    }

    public function testAuthenticateAndRegisterNewUser()
    {
        $usersCount = count($this->users->findAll());
        $email = 'brandnew@email.recodex.test';
        $token = $this->prepareToken('foo', $email, 'John', 'Smith', 'supervisor');
        $user = $this->authenticator->authenticate(self::AUTH_NAME, $token);
        $this->users->refresh($user);
        Assert::equal($email, $user->getEmail());
        Assert::equal('supervisor', $user->getRole());
        Assert::count(1, $user->getExternalLogins());
        $externLogin = $user->getExternalLogins()->first();
        Assert::equal('foo', $externLogin->getExternalId());
        Assert::count($usersCount + 1, $this->users->findAll());
    }

    public function testAuthenticateAndRegisterNewUserFail()
    {
        Assert::exception(function () {
            $token = $this->prepareToken('foo'); // no role
            $res = $this->authenticator->authenticate(self::AUTH_NAME, $token);
        }, WrongCredentialsException::class);
    }

    public function testAuthenticateConnectByEmail()
    {
        $user = $this->getLocalUser();
        $token = $this->prepareToken('foo', $user->getEmail());
        $res = $this->authenticator->authenticate(self::AUTH_NAME, $token);
        $this->users->refresh($res);
        Assert::equal($user->getId(), $res->getId());
        Assert::count(1, $res->getExternalLogins());
        $externLogin = $res->getExternalLogins()->first();
        Assert::equal('foo', $externLogin->getExternalId());
    }

    public function testOldToken()
    {
        Assert::exception(function () {
            $payload = [
                'iat' => time() - 1000,
                'id' => 'foo',
                'mail' => 'notimportant@recodex.test',
            ];
            $token = JWT::encode($payload, self::AUTH_SECRET, "HS256");
            $res = $this->authenticator->authenticate(self::AUTH_NAME, $token);
        }, InvalidExternalTokenException::class);
    }

    public function testInvalidToken()
    {
        Assert::exception(function () {
            $payload = [
                'iat' => time(),
                'id' => 'foo',
                'mail' => 'notimportant@recodex.test',
            ];
            $token = JWT::encode($payload, self::AUTH_SECRET, "HS256");
            $res = $this->authenticator->authenticate(self::AUTH_NAME, $token);
        }, InvalidExternalTokenException::class);
    }
}

(new ExternalServiceAuthenticatorTestCase($container))->run();
