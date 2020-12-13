<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Security\Identity;
use App\Security\TokenScope;
use App\V1Module\Presenters\ForgottenPasswordPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Tester\Assert;


/**
 * @testCase
 */
class TestForgottenPasswordPresenter extends Tester\TestCase
{
    private $userLogin = "user2@example.com";
    private $userPassword = "password2";

    /** @var ForgottenPasswordPresenter */
    protected $presenter;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var  Nette\DI\Container */
    protected $container;

    /** @var Nette\Security\User */
    private $user;

    /** @var App\Security\AccessManager */
    private $accessManager;

    private $originalHttpRequest;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);
        $this->user = $container->getByType(\Nette\Security\User::class);
        $this->accessManager = $container->getByType(\App\Security\AccessManager::class);
        $this->originalHttpRequest = $this->container->getService("http.request");
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
        $this->presenter = PresenterTestHelper::createPresenter($this->container, ForgottenPasswordPresenter::class);
    }

    protected function tearDown()
    {
        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
        Mockery::close();
    }

    public function testResetRequest()
    {
        $this->resetCustomSetup();

        $request = new Nette\Application\Request(
            'V1:ForgottenPassword',
            'POST',
            ['action' => 'default'],
            ['username' => $this->userLogin]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal("OK", $result['payload']);

        $this->resetCustomTeardown();
    }

    private function resetCustomSetup()
    {
        $this->container->removeService("http.request");
        $mockRequest = Mockery::mock($this->originalHttpRequest)  // create proxied partial mock
        ->shouldDeferMissing()
            ->shouldReceive('getPost')->with('username')->andReturn($this->userLogin)->zeroOrMoreTimes()
            ->shouldReceive('getPost')->with('password')->andReturn($this->userPassword)->zeroOrMoreTimes()
            ->shouldReceive('getRemoteAddress')->withNoArgs()->andReturn('localhost')->zeroOrMoreTimes()
            ->getMock();
        $this->container->addService("http.request", $mockRequest);

        $this->presenter = PresenterTestHelper::createPresenter($this->container, ForgottenPasswordPresenter::class);
        $this->presenter->forgottenPasswordHelper = Mockery::mock('App\Helpers\ForgottenPasswordHelper')
            ->shouldReceive('process')
            ->with(Mockery::any(), 'localhost')  // we won't fetch here the Login class
            ->andReturn("")
            ->zeroOrMoreTimes()
            ->getMock();
    }

    private function resetCustomTeardown()
    {
        $this->container->removeService("http.request");
        $this->container->addService("http.request", $this->originalHttpRequest);
    }

    public function testPasswordStrength()
    {
        $request = new Nette\Application\Request(
            'V1:ForgottenPassword',
            'POST',
            ['action' => 'validatePasswordStrength'],
            ['password' => $this->userPassword]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::type("int", $result['payload']['passwordScore']);
    }

    public function testCorrectPasswordReset()
    {
        // issue token for password change in proper scope
        // first log in regulary to find out user ID
        $user = $this->presenter->users->getByEmail($this->userLogin);

        // issue token for changing password
        $token = $this->accessManager->issueToken($user, null, [TokenScope::CHANGE_PASSWORD], 600);
        // login with obtained token
        $this->presenter->user->login(new Identity($user, $this->accessManager->decodeToken($token)));

        $request = new Nette\Application\Request(
            'V1:ForgottenPassword',
            'POST',
            ['action' => 'change'],
            ['password' => "newPassword"]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal("OK", $result['payload']);
    }

    public function testWrongPasswordReset()
    {
        $token = PresenterTestHelper::login($this->container, $this->userLogin);

        $request = new Nette\Application\Request(
            'V1:ForgottenPassword',
            'POST',
            ['action' => 'change'],
            ['password' => "newPassword"]
        );
        Assert::exception(
            function () use ($request) {
                $this->presenter->run($request);
            },
            App\Exceptions\ForbiddenRequestException::class
        );
    }
}

$testCase = new TestForgottenPasswordPresenter();
$testCase->run();
