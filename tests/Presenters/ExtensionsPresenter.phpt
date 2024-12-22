<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Security\TokenScope;
use App\Security\AccessManager;
use App\Helpers\Extensions;
use App\Model\Entity\ExternalLogin;
use App\V1Module\Presenters\ExtensionsPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Tester\Assert;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

/**
 * @testCase
 */
class TestExtensionsPresenter extends Tester\TestCase
{
    /** @var ExtensionsPresenter */
    protected $presenter;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var  Nette\DI\Container */
    protected $container;

    /** @var Nette\Security\User */
    private $user;

    /** @var AccessManager */
    private $accessManager;

    private $extensionsConfig = [];

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);
        $this->user = $container->getByType(\Nette\Security\User::class);
        $this->accessManager = $container->getByType(AccessManager::class);
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
        $this->presenter = PresenterTestHelper::createPresenter($this->container, ExtensionsPresenter::class);
    }

    protected function tearDown()
    {
        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
        Mockery::close();
    }

    private function injectExtension(
        string $id,
        $caption,
        string $url,
        array $scopes = ['master', 'refresh'],
        string $user = null,
        array $instances = [],
        array $roles = [],
        array $externalLogins = [],
    ) {
        $this->extensionsConfig[] = [
            'id' => $id,
            'caption' => $caption,
            'url' => $url,
            'urlTokenExpiration' => 42,
            'token' => [ 'expiration' => 54321, 'scopes' => $scopes, 'user' => $user ],
            'instances' => $instances,
            'user' => [ 'roles' => $roles, 'externalLogins' => $externalLogins ],
        ];

        $this->presenter->extensions = new Extensions($this->extensionsConfig);
    }

    public function testUrl()
    {
        PresenterTestHelper::login($this->container, "submitUser1@example.com");
        $currentUser = PresenterTestHelper::getUser($this->container, "submitUser1@example.com");
        $instanceId = $currentUser->getInstances()->first()->getId();

        $this->injectExtension('test', 'Test', 'https://test.example.com/{token}/{locale}');

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Extensions',
            'GET',
            ['action' => 'url', 'extId' => 'test', 'instanceId' => $instanceId, 'locale' => 'de']
        );

        Assert::type('string', $payload);
        Assert::true(str_starts_with($payload, 'https://test.example.com/'));
        Assert::true(str_ends_with($payload, '/de'));
        $tokens = explode('/', $payload);
        Assert::count(5, $tokens);
        $token = $this->accessManager->decodeToken($tokens[3]);
        Assert::equal($currentUser->getId(), $token->getUserId());
        Assert::equal([TokenScope::EXTENSIONS], $token->getScopes());
        Assert::equal(42, $token->getExpirationTime());
        $data = $token->getPayloadData();
        Assert::equal($instanceId, $data['instance']);
        Assert::equal('test', $data['extension']);
    }

    public function testUrlInstanceFilter()
    {
        PresenterTestHelper::login($this->container, "submitUser1@example.com");
        $currentUser = PresenterTestHelper::getUser($this->container, "submitUser1@example.com");
        $instanceId = $currentUser->getInstances()->first()->getId();

        $this->injectExtension('test', 'Test', 'https://test.example.com/{token}/{locale}', [], null, [ $instanceId ]);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Extensions',
            'GET',
            ['action' => 'url', 'extId' => 'test', 'instanceId' => $instanceId, 'locale' => 'de']
        );

        Assert::type('string', $payload);
        Assert::true(str_starts_with($payload, 'https://test.example.com/'));
        Assert::true(str_ends_with($payload, '/de'));
        $tokens = explode('/', $payload);
        Assert::count(5, $tokens);
        $token = $this->accessManager->decodeToken($tokens[3]);
        Assert::equal($currentUser->getId(), $token->getUserId());
        Assert::equal([TokenScope::EXTENSIONS], $token->getScopes());
        Assert::equal(42, $token->getExpirationTime());
        $data = $token->getPayloadData();
        Assert::equal($instanceId, $data['instance']);
        Assert::equal('test', $data['extension']);
    }

    public function testUrlExtensionUserFilters()
    {
        PresenterTestHelper::login($this->container, "submitUser1@example.com");
        $currentUser = PresenterTestHelper::getUser($this->container, "submitUser1@example.com");
        $login = new ExternalLogin($currentUser, "cas-uk", "12345678");
        $this->em->persist($login);
        $this->em->flush();

        $instanceId = $currentUser->getInstances()->first()->getId();

        $this->injectExtension('test', 'Test', 'https://test.example.com/{token}/{locale}', [], null, [], ['student'], ['cas-uk']);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Extensions',
            'GET',
            ['action' => 'url', 'extId' => 'test', 'instanceId' => $instanceId, 'locale' => 'de']
        );

        Assert::type('string', $payload);
        Assert::true(str_starts_with($payload, 'https://test.example.com/'));
        Assert::true(str_ends_with($payload, '/de'));
        $tokens = explode('/', $payload);
        Assert::count(5, $tokens);
        $token = $this->accessManager->decodeToken($tokens[3]);
        Assert::equal($currentUser->getId(), $token->getUserId());
        Assert::equal([TokenScope::EXTENSIONS], $token->getScopes());
        Assert::equal(42, $token->getExpirationTime());
        $data = $token->getPayloadData();
        Assert::equal($instanceId, $data['instance']);
        Assert::equal('test', $data['extension']);
    }

    public function testUrlNoExtension()
    {
        PresenterTestHelper::login($this->container, "submitUser1@example.com");
        $currentUser = PresenterTestHelper::getUser($this->container, "submitUser1@example.com");
        $instanceId = $currentUser->getInstances()->first()->getId();

        Assert::exception(
            function () use ($instanceId) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:Extensions',
                    'GET',
                    ['action' => 'url', 'extId' => 'test', 'instanceId' => $instanceId, 'locale' => 'de']
                );
            },
            App\Exceptions\ForbiddenRequestException::class
        );
    }

    public function testUrlInvalidRole()
    {
        PresenterTestHelper::login($this->container, "submitUser1@example.com");
        $currentUser = PresenterTestHelper::getUser($this->container, "submitUser1@example.com");
        $instanceId = $currentUser->getInstances()->first()->getId();

        $this->injectExtension('test', 'Test', 'https://', [], null, [], [ 'supervisor' ]);

        Assert::exception(
            function () use ($instanceId) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:Extensions',
                    'GET',
                    ['action' => 'url', 'extId' => 'test', 'instanceId' => $instanceId, 'locale' => 'de']
                );
            },
            App\Exceptions\ForbiddenRequestException::class
        );
    }

    public function testUrlInvalidExternalLogin()
    {
        PresenterTestHelper::login($this->container, "submitUser1@example.com");
        $currentUser = PresenterTestHelper::getUser($this->container, "submitUser1@example.com");
        $login = new ExternalLogin($currentUser, "cas-uk", "12345678");
        $this->em->persist($login);
        $this->em->flush();

        $instanceId = $currentUser->getInstances()->first()->getId();

        $this->injectExtension('test', 'Test', 'https://', [], null, [], [], ['ext1']);

        Assert::exception(
            function () use ($instanceId) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:Extensions',
                    'GET',
                    ['action' => 'url', 'extId' => 'test', 'instanceId' => $instanceId, 'locale' => 'de']
                );
            },
            App\Exceptions\ForbiddenRequestException::class
        );
    }

    public function testToken()
    {
        $currentUser = PresenterTestHelper::getUser($this->container, "submitUser1@example.com");
        $instanceId = $currentUser->getInstances()->first()->getId();
        PresenterTestHelper::login($this->container, "submitUser1@example.com", [TokenScope::EXTENSIONS], 42, [
            "instance" => $instanceId, "extension" => "test"
        ]);

        $this->injectExtension('test', 'Test', 'https://', [ TokenScope::USERS ]);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Extensions',
            'GET',
            ['action' => 'token', 'extId' => 'test']
        );

        Assert::count(2, $payload);
        Assert::equal($currentUser->getId(), $payload["user"]["id"]);
        $token = $this->accessManager->decodeToken($payload["accessToken"]);
        Assert::equal($currentUser->getId(), $token->getUserId());
        Assert::equal([TokenScope::USERS], $token->getScopes());
        Assert::equal(54321, $token->getExpirationTime());
    }

    public function testTokenUserOverride()
    {
        $admin = PresenterTestHelper::getUser($this->container, PresenterTestHelper::ADMIN_LOGIN);
        $currentUser = PresenterTestHelper::getUser($this->container, "submitUser1@example.com");
        $instanceId = $currentUser->getInstances()->first()->getId();
        PresenterTestHelper::login($this->container, "submitUser1@example.com", [TokenScope::EXTENSIONS], 42, [
            "instance" => $instanceId, "extension" => "test"
        ]);

        $this->injectExtension('test', 'Test', 'https://', [ TokenScope::USERS ], $admin->getId());

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:Extensions',
            'GET',
            ['action' => 'token', 'extId' => 'test']
        );

        Assert::count(2, $payload);
        Assert::equal($currentUser->getId(), $payload["user"]["id"]);
        $token = $this->accessManager->decodeToken($payload["accessToken"]);
        Assert::equal($admin->getId(), $token->getUserId());
        Assert::equal([TokenScope::USERS], $token->getScopes());
        Assert::equal(54321, $token->getExpirationTime());
    }

    public function testTokenWrongScope()
    {
        $currentUser = PresenterTestHelper::getUser($this->container, "submitUser1@example.com");
        $instanceId = $currentUser->getInstances()->first()->getId();
        PresenterTestHelper::login($this->container, "submitUser1@example.com", [TokenScope::MASTER], 42, [
            "instance" => $instanceId, "extension" => "test"
        ]);

        $this->injectExtension('test', 'Test', 'https://');

        Assert::exception(
            function () {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:Extensions',
                    'GET',
                    ['action' => 'token', 'extId' => 'test']
                );
            },
            App\Exceptions\ForbiddenRequestException::class
        );
    }

    public function testTokenWrongPayload()
    {
        PresenterTestHelper::login($this->container, "submitUser1@example.com", [TokenScope::EXTENSIONS], 42);

        $this->injectExtension('test', 'Test', 'https://');

        Assert::exception(
            function () {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:Extensions',
                    'GET',
                    ['action' => 'token', 'extId' => 'test']
                );
            },
            App\Exceptions\InvalidArgumentException::class
        );
    }

    public function testTokenWrongExtension()
    {
        $currentUser = PresenterTestHelper::getUser($this->container, "submitUser1@example.com");
        $instanceId = $currentUser->getInstances()->first()->getId();
        PresenterTestHelper::login($this->container, "submitUser1@example.com", [TokenScope::EXTENSIONS], 42, [
            "instance" => $instanceId, "extension" => "ext1"
        ]);

        $this->injectExtension('test', 'Test', 'https://');

        Assert::exception(
            function () {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:Extensions',
                    'GET',
                    ['action' => 'token', 'extId' => 'test']
                );
            },
            App\Exceptions\BadRequestException::class
        );
    }

    public function testTokenWrongInstance()
    {
        $currentUser = PresenterTestHelper::getUser($this->container, "submitUser1@example.com");
        $instanceId = $currentUser->getInstances()->first()->getId();
        PresenterTestHelper::login($this->container, "submitUser1@example.com", [TokenScope::EXTENSIONS], 42, [
            "instance" => '', "extension" => "test"
        ]);

        $this->injectExtension('test', 'Test', 'https://');

        Assert::exception(
            function () {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:Extensions',
                    'GET',
                    ['action' => 'token', 'extId' => 'test']
                );
            },
            App\Exceptions\BadRequestException::class
        );
    }

    public function testTokenNoExtension()
    {
        $currentUser = PresenterTestHelper::getUser($this->container, "submitUser1@example.com");
        $instanceId = $currentUser->getInstances()->first()->getId();
        PresenterTestHelper::login($this->container, "submitUser1@example.com", [TokenScope::EXTENSIONS], 42, [
            "instance" => $instanceId, "extension" => "test"
        ]);

        Assert::exception(
            function () {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:Extensions',
                    'GET',
                    ['action' => 'token', 'extId' => 'test']
                );
            },
            App\Exceptions\ForbiddenRequestException::class
        );
    }
}

$testCase = new TestExtensionsPresenter();
$testCase->run();
