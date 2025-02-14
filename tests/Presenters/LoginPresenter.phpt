<?php

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\WrongCredentialsException;
use App\Helpers\ExternalLogin\ExternalServiceAuthenticator;
use App\Security\AccessToken;
use App\Security\Identity;
use App\Security\TokenScope;
use App\Model\Entity\SecurityEvent;
use App\V1Module\Presenters\LoginPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Application\Request;
use Nette\Application\Responses\JsonResponse;
use Firebase\JWT\JWT;
use Tester\Assert;

$container = require_once __DIR__ . "/../bootstrap.php";
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

/**
 * @testCase
 */
class TestLoginPresenter extends Tester\TestCase
{
    private $userLogin = "user2@example.com";
    private $userPassword = "password2";

    /** @var LoginPresenter */
    protected $presenter;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var  Nette\DI\Container */
    protected $container;

    /** @var Nette\Security\User */
    private $user;

    /** @var \App\Model\Repository\Users */
    private $users;

    /** @var \App\Model\Repository\Logins */
    private $logins;

    /** @var \App\Model\Repository\ExternalLogins */
    private $externalLogins;

    /** @var \App\Model\Repository\Instances */
    private $instances;

    /** @var \App\Helpers\EmailVerificationHelper */
    private $emailVerificationHelper;

    /** @var \App\Helpers\FailureHelper */
    private $failureHelper;

    public function __construct($container)
    {
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);
        $this->user = $container->getByType(\Nette\Security\User::class);
        $this->users = $container->getByType(\App\Model\Repository\Users::class);
        $this->logins = $container->getByType(\App\Model\Repository\Logins::class);
        $this->externalLogins = $container->getByType(\App\Model\Repository\ExternalLogins::class);
        $this->instances = $container->getByType(\App\Model\Repository\Instances::class);
        $this->emailVerificationHelper = $container->getByType(\App\Helpers\EmailVerificationHelper::class);
        $this->failureHelper = $container->getByType(App\Helpers\FailureHelper::class);
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
        $this->presenter = PresenterTestHelper::createPresenter($this->container, LoginPresenter::class);
    }

    protected function tearDown()
    {
        $this->user->logout(true);
        Mockery::close();
    }

    public function testLogin()
    {
        $events = $this->presenter->securityEvents->findAll();
        Assert::count(0, $events);

        $request = new Request(
            "V1:Login",
            "POST",
            ["action" => "default"],
            [
                "username" => $this->userLogin,
                "password" => $this->userPassword
            ]
        );

        /** @var JsonResponse $response */
        $response = $this->presenter->run($request);
        Assert::type(JsonResponse::class, $response);
        $result = $response->getPayload();

        Assert::same(200, $result["code"]);
        Assert::true(array_key_exists("accessToken", $result["payload"]));
        Assert::same($this->presenter->users->getByEmail($this->userLogin)->getId(), $result["payload"]["user"]["id"]);
        Assert::true($this->presenter->user->isLoggedIn());

        $events = $this->presenter->securityEvents->findAll();
        Assert::count(1, $events);
        Assert::equal(SecurityEvent::TYPE_LOGIN, $events[0]->getType());
        Assert::equal($this->presenter->user->getId(), $events[0]->getUser()->getId());
    }

    public function testLoginIncorrect()
    {
        $request = new Request(
            "V1:Login",
            "POST",
            ["action" => "default"],
            [
                "username" => $this->userLogin,
                "password" => $this->userPassword . "42"
            ]
        );

        Assert::exception(
            function () use ($request) {
                $this->presenter->run($request);
            },
            WrongCredentialsException::class
        );

        Assert::false($this->presenter->user->isLoggedIn());
    }

    public function testLoginExternal()
    {
        $events = $this->presenter->securityEvents->findAll();
        Assert::count(0, $events);

        $authenticator = new ExternalServiceAuthenticator(
            [[
                'name' => 'test-cas',
                'jwtSecret' => 'tajnyRetezec',
            ]],
            $this->externalLogins,
            $this->users,
            $this->logins,
            $this->instances,
            $this->emailVerificationHelper,
            $this->failureHelper
        );

        $user = $this->presenter->users->getByEmail($this->userLogin);

        $payload = [
            'iat' => time(),
            'id' => 'external-id-1',
            'mail' => $this->userLogin,
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
        ];
        $token = JWT::encode($payload, 'tajnyRetezec', "HS256");

        $this->presenter->externalServiceAuthenticator = $authenticator;

        $request = new Request("V1:Login", "POST", ["action" => "external", "authenticatorName" => "test-cas"], ['token' => $token]);

        $response = $this->presenter->run($request);
        Assert::type(JsonResponse::class, $response);
        $result = $response->getPayload();

        Assert::same(200, $result["code"]);
        Assert::true(array_key_exists("accessToken", $result["payload"]));
        Assert::equal($user->getId(), $result["payload"]["user"]["id"]);
        Assert::true($this->presenter->user->isLoggedIn());

        $events = $this->presenter->securityEvents->findAll();
        Assert::count(1, $events);
        Assert::equal(SecurityEvent::TYPE_LOGIN_EXTERNAL, $events[0]->getType());
        Assert::equal($user->getId(), $events[0]->getUser()->getId());
    }

    public function testTakeover()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = $this->presenter->users->getByEmail($this->userLogin);

        $request = new Nette\Application\Request(
            'V1:Login',
            'POST',
            ['action' => 'takeOver', 'userId' => $user->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::same(200, $result["code"]);

        Assert::true(array_key_exists("accessToken", $result["payload"]));
        Assert::equal($user->getId(), $result["payload"]["user"]["id"]);
    }

    public function testTakeoverIncorrect()
    {
        $user = $this->presenter->users->getByEmail($this->userLogin);

        $request = new Nette\Application\Request(
            'V1:Login',
            'POST',
            ['action' => 'takeOver', 'userId' => $user->getId()]
        );

        Assert::exception(
            function () use ($request) {
                $this->presenter->run($request);
            },
            ForbiddenRequestException::class
        );
    }

    public function testRefresh()
    {
        $events = $this->presenter->securityEvents->findAll();
        Assert::count(0, $events);

        $user = $this->presenter->users->getByEmail($this->userLogin);
        $time = time();
        $token = new AccessToken(
            (object)[
                "scopes" => [TokenScope::REFRESH, "hello", "world"],
                "sub" => $user->getId(),
                "exp" => $time + 1200,
                "ref" => $time + 2400,
                "iat" => $time - 1200
            ]
        );

        $this->presenter->user->login(new Identity($user, $token));

        $request = new Request("V1:Login", "POST", ["action" => "refresh"], []);

        /** @var JsonResponse $response */
        $response = $this->presenter->run($request);
        Assert::type(JsonResponse::class, $response);
        $result = $response->getPayload();

        Assert::same(200, $result["code"]);
        Assert::true(array_key_exists("accessToken", $result["payload"]));
        Assert::same($user->getId(), $result["payload"]["user"]["id"]);
        Assert::true($this->presenter->user->isLoggedIn());

        $newToken = $this->presenter->accessManager->decodeToken($result["payload"]["accessToken"]);
        Assert::true($newToken->isInScope(TokenScope::REFRESH));
        Assert::true($newToken->isInScope("hello"));
        Assert::true($newToken->isInScope("world"));

        $events = $this->presenter->securityEvents->findAll();
        Assert::count(1, $events);
        Assert::equal(SecurityEvent::TYPE_REFRESH, $events[0]->getType());
        Assert::equal($user->getId(), $events[0]->getUser()->getId());
    }

    public function testRefreshWrongScope()
    {
        $user = $this->presenter->users->getByEmail($this->userLogin);
        $time = time();
        $token = new AccessToken(
            (object)[
                "scopes" => [],
                "sub" => $user->getId(),
                "exp" => $time + 1200,
                "ref" => $time + 2400,
                "iat" => $time - 1200
            ]
        );

        $this->presenter->user->login(new Identity($user, $token));
        $request = new Request("V1:Login", "POST", ["action" => "refresh"], []);

        Assert::exception(
            function () use ($request) {
                $this->presenter->run($request);
            },
            ForbiddenRequestException::class
        );
    }

    public function testIssueToken()
    {
        $events = $this->presenter->securityEvents->findAll();
        Assert::count(0, $events);

        PresenterTestHelper::loginDefaultAdmin($this->container);
        $request = new Request(
            "V1:Login",
            "POST",
            ["action" => "issueRestrictedToken"],
            ["scopes" => [TokenScope::REFRESH, "read-all"], "expiration" => "3000"]
        );
        $response = $this->presenter->run($request);
        Assert::type(JsonResponse::class, $response);
        $payload = $response->getPayload()["payload"];
        $token = $this->presenter->accessManager->decodeToken($payload["accessToken"]);
        Assert::true($token->isInScope(TokenScope::REFRESH));
        Assert::true($token->isInScope("read-all"));

        $events = $this->presenter->securityEvents->findAll();
        Assert::count(1, $events);
        Assert::equal(SecurityEvent::TYPE_ISSUE_TOKEN, $events[0]->getType());
        Assert::equal($this->presenter->user->getId(), $events[0]->getUser()->getId());
    }
}

(new TestLoginPresenter($container))->run();
