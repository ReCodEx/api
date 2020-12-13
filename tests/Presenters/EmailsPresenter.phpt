<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Helpers\EmailHelper;
use App\Model\Entity\User;
use App\Security\Roles;
use App\V1Module\Presenters\EmailsPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Tester\Assert;


/**
 * @testCase
 */
class TestEmailsPresenter extends Tester\TestCase
{
    /** @var EmailsPresenter */
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
        $this->presenter = PresenterTestHelper::createPresenter($this->container, EmailsPresenter::class);
    }

    protected function tearDown()
    {
        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
    }

    public function testSendToAll()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $subject = "Subject - ";
        $message = "New email message";

        $emails = array_map(
            function (User $user) {
                return $user->getEmail();
            },
            $this->presenter->users->findAll()
        );

        /** @var Mockery\Mock | EmailHelper $mockEmailHelper */
        $mockEmailHelper = Mockery::mock(EmailHelper::class);
        $mockEmailHelper->shouldReceive("sendFromDefault")
            ->withArgs([[], "en", $subject, $message, $emails])->andReturn(true)->once();
        $this->presenter->emailHelper = $mockEmailHelper;

        $request = new Nette\Application\Request(
            'V1:Emails', 'POST', ['action' => 'default'],
            [
                "subject" => $subject,
                "message" => $message
            ]
        );

        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal("OK", $result['payload']);
    }

    public function testSendToSupervisors()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $subject = "Subject supervisors - ";
        $message = "New email message for supervisors";

        $emails = array_map(
            function (User $user) {
                return $user->getEmail();
            },
            $this->presenter->users->findByRoles(Roles::SUPERVISOR_ROLE, Roles::SUPERADMIN_ROLE)
        );

        /** @var Mockery\Mock | EmailHelper $mockEmailHelper */
        $mockEmailHelper = Mockery::mock(EmailHelper::class);
        $mockEmailHelper->shouldReceive("sendFromDefault")
            ->withArgs([[], "en", $subject, $message, $emails])->andReturn(true)->once();
        $this->presenter->emailHelper = $mockEmailHelper;

        $request = new Nette\Application\Request(
            'V1:Emails', 'POST', ['action' => 'sendToSupervisors'],
            [
                "subject" => $subject,
                "message" => $message
            ]
        );

        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal("OK", $result['payload']);
    }

    public function testSendToRegularUsers()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $subject = "Subject regular users - ";
        $message = "New email message for regular users";

        $emails = array_map(
            function (User $user) {
                return $user->getEmail();
            },
            $this->presenter->users->findByRoles(Roles::STUDENT_ROLE)
        );

        /** @var Mockery\Mock | EmailHelper $mockEmailHelper */
        $mockEmailHelper = Mockery::mock(EmailHelper::class);
        $mockEmailHelper->shouldReceive("sendFromDefault")
            ->withArgs([[], "en", $subject, $message, $emails])->andReturn(true)->once();
        $this->presenter->emailHelper = $mockEmailHelper;

        $request = new Nette\Application\Request(
            'V1:Emails', 'POST', ['action' => 'sendToRegularUsers'],
            [
                "subject" => $subject,
                "message" => $message
            ]
        );

        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal("OK", $result['payload']);
    }

    public function testSendToGroupMembers()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $group = current($this->presenter->groups->findAll());
        $subject = "Subject group - ";
        $message = "New email message for group";

        $emails = array_map(
            function (User $user) {
                return $user->getEmail();
            },
            $group->getMembers()->getValues()
        );
        $emails[] = PresenterTestHelper::ADMIN_LOGIN;

        /** @var Mockery\Mock | EmailHelper $mockEmailHelper */
        $mockEmailHelper = Mockery::mock(EmailHelper::class);
        $mockEmailHelper->shouldReceive("sendFromDefault")
            ->withArgs([[], "en", $subject, $message, $emails])->andReturn(true)->once();
        $this->presenter->emailHelper = $mockEmailHelper;

        $request = new Nette\Application\Request(
            'V1:Emails', 'POST',
            ['action' => 'sendToGroupMembers', 'groupId' => $group->getId()],
            [
                "toSupervisors" => true,
                "toAdmins" => true,
                "toMe" => true,
                "subject" => $subject,
                "message" => $message
            ]
        );

        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal("OK", $result['payload']);
    }


}

$testCase = new TestEmailsPresenter();
$testCase->run();
