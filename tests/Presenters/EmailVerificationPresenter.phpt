<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Security\Identity;
use App\Security\AccessToken;
use App\Helpers\EmailVerificationHelper;
use App\Security\TokenScope;
use App\V1Module\Presenters\EmailVerificationPresenter;
use App\V1Module\Presenters\GroupsPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Tester\Assert;


/**
 * @testCase
 */
class TestEmailVerificationPresenter extends Tester\TestCase
{
    /** @var GroupsPresenter */
    protected $presenter;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var  Nette\DI\Container */
    protected $container;

    /** @var Nette\Security\User */
    private $user;

    /** @var \App\Security\AccessManager */
    private $accessManager;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);
        $this->user = $container->getByType(\Nette\Security\User::class);
        $this->accessManager = $container->getByType(\App\Security\AccessManager::class);
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
        $this->presenter = PresenterTestHelper::createPresenter($this->container, EmailVerificationPresenter::class);
    }

    protected function tearDown()
    {
        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
    }

    public function testResendVerificationEmail()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = $this->presenter->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

        /** @var Mockery\Mock | EmailHelper $mockEmailVerificationHelper */
        $mockEmailVerificationHelper = Mockery::mock(EmailVerificationHelper::class);
        $mockEmailVerificationHelper->shouldReceive("process")->with($user)->andReturn(true);
        $this->presenter->emailVerificationHelper = $mockEmailVerificationHelper;

        $request = new Nette\Application\Request(
            'V1:EmailVerification',
            'POST',
            ['action' => 'resendVerificationEmail']
        );

        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal("OK", $result['payload']);
    }

    public function testEmailVerification()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = $this->presenter->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

        // prepare token for email verification
        $token = $this->accessManager->issueToken(
            $user,
            null,
            [TokenScope::EMAIL_VERIFICATION],
            600,
            ["email" => $user->getEmail()]
        );
        // login with obtained token
        $this->presenter->user->login(new Identity($user, $this->accessManager->decodeToken($token)));

        $request = new Nette\Application\Request(
            'V1:EmailVerification',
            'POST',
            ['action' => 'emailVerification']
        );

        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal("OK", $result['payload']);
    }

}

$testCase = new TestEmailVerificationPresenter();
$testCase->run();
