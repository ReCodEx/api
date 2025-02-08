<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\ForbiddenRequestException;
use App\Helpers\EmailVerificationHelper;
use App\Helpers\AnonymizationHelper;
use App\Model\Entity\Exercise;
use App\Model\Entity\Login;
use App\Model\Entity\ExternalLogin;
use App\Model\Entity\User;
use App\Model\Entity\SecurityEvent;
use App\Model\Repository\Logins;
use App\Model\Repository\ExternalLogins;
use App\Model\Repository\Users;
use App\Model\View\UserViewFactory;
use App\Security\Roles;
use App\V1Module\Presenters\UsersPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Tester\Assert;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

/**
 * @httpCode any
 * @testCase
 */
class TestUsersPresenter extends Tester\TestCase
{
    /** @var UsersPresenter */
    protected $presenter;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var Nette\Security\User */
    private $user;

    /** @var string */
    private $presenterPath = "V1:Users";

    /** @var App\Model\Repository\Users */
    protected $users;

    /** @var App\Model\Repository\Logins */
    protected $logins;

    /** @var App\Model\Repository\ExternalLogins */
    protected $externalLogins;

    /** @var App\Helpers\AnonymizationHelper */
    protected $anonymizationHelper;

    /** @var  Nette\DI\Container */
    protected $container;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);
        $this->user = $container->getByType(\Nette\Security\User::class);
        $this->users = $container->getByType(Users::class);
        $this->logins = $container->getByType(Logins::class);
        $this->externalLogins = $container->getByType(ExternalLogins::class);
        $this->anonymizationHelper = $container->getByType(AnonymizationHelper::class);
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
        $this->presenter = PresenterTestHelper::createPresenter($this->container, UsersPresenter::class);
    }

    protected function tearDown()
    {
        Mockery::close();

        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
    }

    public function testGetAllUsers()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $request = new Nette\Application\Request($this->presenterPath, 'GET', ['action' => 'default']);
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::true(count($result['payload']['items']) > 0);
        Assert::true(array_key_exists("offset", $result['payload']));
        Assert::true(array_key_exists('limit', $result['payload']));
        Assert::true(array_key_exists('totalCount', $result['payload']));
        Assert::true(array_key_exists('orderBy', $result['payload']));
        Assert::true(array_key_exists('filters', $result['payload']));
        Assert::count((int)$result['payload']['totalCount'], $result['payload']['items']);

        $users = $result['payload']['items'];
        foreach ($users as $user) {
            Assert::true(array_key_exists("id", $user));
            Assert::true(array_key_exists("fullName", $user));
            Assert::true(array_key_exists("privateData", $user));
        }
    }

    public function testGetAllSuperadmins()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = $this->presenter->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            ['action' => 'default', 'filters' => ['roles' => ['superadmin']]]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::count(1, $result['payload']['items'], json_encode($result['payload']));
        Assert::equal($user->getName(), $result['payload']['items'][0]['fullName']);
    }

    public function testGetAllOrderBy()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = $this->presenter->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            ['action' => 'default', 'orderBy' => '!name']
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal($user->getName(), $result['payload']['items'][$result['payload']['totalCount'] - 1]['fullName']);
    }

    public function testGetAllPaginated()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        // Get all users ordered by email
        $requestAll = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            ['action' => 'default', 'orderBy' => 'email']
        );
        $responseAll = $this->presenter->run($requestAll);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $responseAll);
        $resultAll = $responseAll->getPayload();
        Assert::equal(200, $resultAll['code']);

        // Get a page of users ordered by email
        $request = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            ['action' => 'default', 'orderBy' => 'email', 'offset' => 4, 'limit' => 3]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);
        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        // Compare the paginated result with slice from all
        Assert::equal($result['payload']['totalCount'], $resultAll['payload']['totalCount']);
        $offset = 4;
        foreach ($result['payload']['items'] as $user) {
            Assert::equal($user['id'], $resultAll['payload']['items'][$offset++]['id']);
        }
    }

    public function testGetListUsersByIds()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $users = $this->presenter->users->findAll();
        $first = $users[0];
        $second = $users[1];

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'POST',
            ['action' => 'listByIds'],
            ['ids' => [$first->getId(), $second->getId()]]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::count(2, $result['payload']);

        $users = $result['payload'];
        foreach ($users as $user) {
            Assert::true(array_key_exists("id", $user));
            Assert::true(array_key_exists("fullName", $user));
            Assert::true(array_key_exists("privateData", $user));
        }
    }

    public function testDetail()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            ['action' => 'detail', 'id' => $user->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        Assert::same($user->getId(), $result["payload"]["id"]);
    }

    public function testUpdateProfileWithoutEmailAndPassword()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

        $firstName = "firstNameUpdated";
        $lastName = "lastNameUpdated";
        $titlesBeforeName = "titlesBeforeNameUpdated";
        $titlesAfterName = "titlesAfterNameUpdated";

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'POST',
            ['action' => 'updateProfile', 'id' => $user->getId()],
            [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'titlesBeforeName' => $titlesBeforeName,
                'titlesAfterName' => $titlesAfterName,
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        $updatedUser = $result["payload"]["user"];
        Assert::equal("$titlesBeforeName $firstName $lastName $titlesAfterName", $updatedUser["fullName"]);
        Assert::null($updatedUser["avatarUrl"]);

        $storedUpdatedUser = $this->users->get($user->getId());
        Assert::same($updatedUser["id"], $storedUpdatedUser->getId());
    }

    public function testUpdateProfileWithEmailAndWithoutPassword()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

        $firstName = "firstNameUpdated";
        $lastName = "lastNameUpdated";
        $titlesBeforeName = "titlesBeforeNameUpdated";
        $titlesAfterName = "titlesAfterNameUpdated";
        $email = "new-email@recodex.mff.cuni.cz";

        $emailVerificationHelper = Mockery::mock(EmailVerificationHelper::class);
        $emailVerificationHelper->shouldReceive("process")->with($user)->andReturn()->once();
        $this->presenter->emailVerificationHelper = $emailVerificationHelper;

        $user->setGravatar(true);
        $this->presenter->users->persist($user);

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'POST',
            ['action' => 'updateProfile', 'id' => $user->getId()],
            [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'titlesBeforeName' => $titlesBeforeName,
                'titlesAfterName' => $titlesAfterName,
                'email' => $email,
                'gravatarUrlEnabled' => false // make sure gravatar gets reset
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        $updatedUser = $result["payload"]["user"];
        Assert::equal("$titlesBeforeName $firstName $lastName $titlesAfterName", $updatedUser["fullName"]);
        Assert::equal($email, $updatedUser["privateData"]["email"]);
        Assert::null($updatedUser["avatarUrl"]);

        $storedUpdatedUser = $this->users->get($user->getId());
        Assert::same($updatedUser["id"], $storedUpdatedUser->getId());
    }

    public function testUpdateProfileWithoutEmailAndWithPassword()
    {
        $events = $this->presenter->securityEvents->findAll();
        Assert::count(0, $events);

        PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);
        $login = $this->presenter->logins->findByUsernameOrThrow($user->getEmail());

        $user->setGravatar(true);
        $this->presenter->users->persist($user);

        $firstName = "firstNameUpdated";
        $lastName = "lastNameUpdated";
        $titlesBeforeName = "titlesBeforeNameUpdated";
        $titlesAfterName = "titlesAfterNameUpdated";
        $oldPassword = "admin";
        $password = "newPassword";
        $passwordConfirm = "newPassword";

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'POST',
            ['action' => 'updateProfile', 'id' => $user->getId()],
            [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'titlesBeforeName' => $titlesBeforeName,
                'titlesAfterName' => $titlesAfterName,
                'oldPassword' => $oldPassword,
                'password' => $password,
                'passwordConfirm' => $passwordConfirm,
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        $updatedUser = $result["payload"]["user"];
        Assert::equal("$titlesBeforeName $firstName $lastName $titlesAfterName", $updatedUser["fullName"]);
        Assert::true($login->passwordsMatchOrEmpty($password, $this->presenter->passwordsService));
        Assert::true($updatedUser["avatarUrl"] !== null); // gravatar was not reset

        $storedUpdatedUser = $this->users->get($user->getId());
        Assert::equal($updatedUser["id"], $storedUpdatedUser->getId());

        $events = $this->presenter->securityEvents->findAll();
        Assert::count(1, $events);
        Assert::equal(SecurityEvent::TYPE_CHANGE_PASSWORD, $events[0]->getType());
        Assert::equal($updatedUser["id"], $events[0]->getUser()->getId());
    }

    public function testUpdateProfileWithoutNewPassword()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'POST',
            ['action' => 'updateProfile', 'id' => $user->getId()],
            [
                'firstName' => "firstNameUpdated",
                'lastName' => "lastNameUpdated",
                'titlesBeforeName' => "titlesBeforeNameUpdated",
                'titlesAfterName' => "titlesAfterNameUpdated",
                'oldPassword' => "admin",
                'passwordConfirm' => "newPassword",
                'gravatarUrlEnabled' => false
            ]
        );

        Assert::exception(
            function () use ($request) {
                $this->presenter->run($request);
            },
            App\Exceptions\InvalidArgumentException::class
        );
    }

    public function testUpdateProfileWithoutNewPasswordConfirm()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'POST',
            ['action' => 'updateProfile', 'id' => $user->getId()],
            [
                'firstName' => "firstNameUpdated",
                'lastName' => "lastNameUpdated",
                'titlesBeforeName' => "titlesBeforeNameUpdated",
                'titlesAfterName' => "titlesAfterNameUpdated",
                'oldPassword' => "admin",
                'password' => "newPassword",
                'gravatarUrlEnabled' => false
            ]
        );

        Assert::exception(
            function () use ($request) {
                $this->presenter->run($request);
            },
            App\Exceptions\InvalidArgumentException::class
        );
    }

    public function testUpdateProfileWithoutFirstAndLastName()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

        $titlesBeforeName = "titlesBeforeNameUpdated";
        $titlesAfterName = "titlesAfterNameUpdated";

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'POST',
            ['action' => 'updateProfile', 'id' => $user->getId()],
            [
                'titlesBeforeName' => $titlesBeforeName,
                'titlesAfterName' => $titlesAfterName,
                'gravatarUrlEnabled' => true
            ]
        );

        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        $updatedUser = $result["payload"]["user"];
        Assert::equal(
            "$titlesBeforeName {$user->getFirstName()} {$user->getLastName()} $titlesAfterName",
            $updatedUser["fullName"]
        );
        Assert::true($updatedUser["avatarUrl"] !== null);
    }

    public function testForceUpdatePassword()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = $this->users->getByEmail(PresenterTestHelper::GROUP_SUPERVISOR_LOGIN);
        $newPassword = 'secretPasswd';

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            $this->presenterPath,
            'POST',
            ['action' => 'updateProfile', 'id' => $user->getId()],
            [
                'titlesBeforeName' => '',
                'titlesAfterName' => '',
                'gravatarUrlEnabled' => null,
                'password' => $newPassword,
                'passwordConfirm' => $newPassword,
            ]
        );

        $updatedUser = $payload["user"];
        Assert::equal($updatedUser["privateData"]["email"], PresenterTestHelper::GROUP_SUPERVISOR_LOGIN);
        Assert::null($updatedUser["avatarUrl"]);

        $login = $this->logins->findByUsernameOrThrow($user->getEmail());
        Assert::true($login->passwordsMatch($newPassword, $this->presenter->passwordsService));
    }

    public function testCannotSelfForceUpdatePassword()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);
        $newPassword = 'secretPasswd';

        Assert::exception(
            function () use ($user, $newPassword) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    $this->presenterPath,
                    'POST',
                    ['action' => 'updateProfile', 'id' => $user->getId()],
                    [
                        'titlesBeforeName' => '',
                        'titlesAfterName' => '',
                        'gravatarUrlEnabled' => false,
                        'password' => $newPassword,
                        'passwordConfirm' => $newPassword,
                    ]
                );
            },
            App\Exceptions\WrongCredentialsException::class
        );
    }

    public function testUpdateSettings()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        // Testing hack
        // User view factory remembers logged in user (so we need to reset it after login)
        $this->presenter->userViewFactory = new UserViewFactory(
            $this->container->getByType(\App\Security\ACL\IUserPermissions::class),
            $this->container->getByType(\App\Model\Repository\Logins::class),
            $this->container->getByType(\Nette\Security\User::class)
        );

        $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

        $defaultLanguage = "de";
        $newAssignmentEmails = false;
        $assignmentDeadlineEmails = false;
        $submissionEvaluatedEmails = false;

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'POST',
            ['action' => 'updateSettings', 'id' => $user->getId()],
            [
                'defaultLanguage' => $defaultLanguage,
                'newAssignmentEmails' => $newAssignmentEmails,
                'assignmentDeadlineEmails' => $assignmentDeadlineEmails,
                'submissionEvaluatedEmails' => $submissionEvaluatedEmails
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        $user = $result["payload"];
        $settings = $user["privateData"]["settings"];
        Assert::equal($defaultLanguage, $settings->getDefaultLanguage());
        Assert::equal($newAssignmentEmails, $settings->getNewAssignmentEmails());
        Assert::equal($assignmentDeadlineEmails, $settings->getAssignmentDeadlineEmails());
        Assert::equal($submissionEvaluatedEmails, $settings->getSubmissionEvaluatedEmails());
    }

    public function testUpdateUiData()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        // Testing hack
        // User view factory remembers logged in user (so we need to reset it after login)
        $this->presenter->userViewFactory = new UserViewFactory(
            $this->container->getByType(\App\Security\ACL\IUserPermissions::class),
            $this->container->getByType(\App\Model\Repository\Logins::class),
            $this->container->getByType(\Nette\Security\User::class)
        );

        $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);
        $uiData = [
            'lastSelectedGroup' => '1234',
            'stretcherSize' => 42,
            'nestedStructure' => [
                'pos1' => 19,
                'pos2' => 33,
                'open' => false,
            ],
        ];
        $uiData2 = [
            'lastSelected' => 'abcd',
            'size' => 42,
            'foo' => null
        ];

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            $this->presenterPath,
            'POST',
            ['action' => 'updateUiData', 'id' => $user->getId()],
            ['uiData' => $uiData]
        );
        Assert::equal($uiData, $payload["privateData"]["uiData"]);

        $nested = ['pos1' => 0];
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            $this->presenterPath,
            'POST',
            ['action' => 'updateUiData', 'id' => $user->getId()],
            ['uiData' => ['stretcherSize' => 54, 'nestedStructure' => $nested]]
        );
        $uiData['stretcherSize'] = 54;
        $uiData['nestedStructure'] = $nested;
        Assert::equal($uiData, $payload["privateData"]["uiData"]);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            $this->presenterPath,
            'POST',
            ['action' => 'updateUiData', 'id' => $user->getId()],
            ['uiData' => $uiData2, 'overwrite' => true]
        );
        Assert::equal($uiData2, $payload["privateData"]["uiData"]);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            $this->presenterPath,
            'POST',
            ['action' => 'updateUiData', 'id' => $user->getId()],
            ['uiData' => null]
        );
        Assert::equal($uiData2, $payload["privateData"]["uiData"]);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            $this->presenterPath,
            'POST',
            ['action' => 'updateUiData', 'id' => $user->getId()],
            ['uiData' => null, 'overwrite' => true]
        );
        Assert::null($payload["privateData"]["uiData"]);
    }

    public function testCreateLocalAccount()
    {
        $instance = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN)->getInstances()->first();
        $user = new User("external@external.external", "firstName", "lastName", "", "", "student", $instance);
        $user->setVerified();
        $external = new ExternalLogin($user, "test", $user->getEmail());

        $this->users->persist($user);
        $this->externalLogins->persist($external);

        PresenterTestHelper::login($this->container, $user->getEmail());

        // pre-test condition
        Assert::equal(false, $user->hasLocalAccount());

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'POST',
            ['action' => 'createLocalAccount', 'id' => $user->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        $payload = $result["payload"];
        Assert::equal($user->getId(), $payload["id"]);
        Assert::equal(true, $payload["privateData"]["isLocal"]);
    }

    public function testSupervisorGroups()
    {
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            ['action' => 'groups', 'id' => $user->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::count(3, $result["payload"]);

        Assert::true(array_key_exists("supervisor", $result["payload"]));
        $supervisorIn = $result["payload"]["supervisor"];

        $groupsAsSupervisor = $user->getGroupsAsSupervisor()->filter(
            function ($group) {
                return !$group->isArchived();
            }
        )->getValues();
        $correctIds = PresenterTestHelper::extractIdsMap($groupsAsSupervisor);
        $payloadIds = PresenterTestHelper::extractIdsMap($supervisorIn);
        Assert::equal(count($correctIds), count($payloadIds));
    }

    public function testStudentGroups()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = $this->users->getByEmail(PresenterTestHelper::STUDENT_GROUP_MEMBER_LOGIN);

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            ['action' => 'groups', 'id' => $user->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::count(3, $result["payload"]);

        Assert::true(array_key_exists("student", $result["payload"]));
        $studentIn = $result["payload"]["student"];
        $expectedStudentIn = $user->getGroupsAsStudent()->getValues();
        Assert::equal($this->presenter->groupViewFactory->getGroups($expectedStudentIn), $studentIn);

        Assert::true(array_key_exists("stats", $result["payload"]));
        $stats = $result["payload"]["stats"];
        Assert::count(count($expectedStudentIn), $stats);

        foreach ($stats as $stat) {
            Assert::count(7, $stat);
            Assert::true(array_key_exists("userId", $stat));
            Assert::true(array_key_exists("groupId", $stat));
            Assert::true(array_key_exists("assignments", $stat));
            Assert::true(array_key_exists("shadowAssignments", $stat));
            Assert::true(array_key_exists("points", $stat));
            Assert::true(array_key_exists("hasLimit", $stat));
            Assert::true(array_key_exists("passesLimit", $stat));
        }
    }

    public function testInstances()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            ['action' => 'instances', 'id' => $user->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);

        $instances = $result["payload"];
        Assert::equal(1, count($instances));

        $instance = array_pop($instances);
        Assert::equal($user->getInstances()->first()->getId(), $instance['id']);
    }

    public function testUnauthenticatedUserCannotViewUserDetail()
    {
        $user = $this->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            ['action' => 'detail', 'id' => $user->getId()]
        );

        Assert::exception(
            function () use ($request) {
                $this->presenter->run($request);
            },
            ForbiddenRequestException::class
        );
    }

    public function testDeleteUser()
    {
        $victim = "user2@example.com";
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $user = $this->users->getByEmail($victim);
        Assert::truthy($user);
        $userId = $user->getId();
        Assert::type(Login::class, $this->logins->getByUsername($victim));

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'DELETE',
            ['action' => 'delete', 'id' => $userId]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::null($this->users->getByEmail($victim));
        Assert::null($this->logins->getByUsername($victim));

        $deletedUser = $this->users->findOneByEvenIfDeleted(['id' => $userId]);
        Assert::truthy($deletedUser);
        Assert::true($deletedUser->isDeleted());
        $suffix = $this->anonymizationHelper->getDeletedEmailSuffix();
        Assert::same(
            $suffix,
            substr($deletedUser->getEmail(), -strlen($suffix))
        );  // negative offset ~ suffix instead of prefix
    }

    public function testSetRoleUser()
    {
        $victim = "user2@example.com";
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = $this->users->getByEmail($victim);
        Assert::equal(Roles::STUDENT_ROLE, $user->getRole());

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'POST',
            ['action' => 'setRole', 'id' => $user->getId()],
            ['role' => Roles::SUPERVISOR_ROLE]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal(Roles::SUPERVISOR_ROLE, $this->users->getByEmail($victim)->getRole());
    }

    public function testSetAllowedUser()
    {
        $victim = "user2@example.com";
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = $this->users->getByEmail($victim);
        Assert::true($user->isAllowed());

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            $this->presenterPath,
            'POST',
            ['action' => 'setAllowed', 'id' => $user->getId()],
            ['isAllowed' => 0]
        );

        Assert::same($user->getId(), $payload['id']);
        Assert::false($payload['privateData']['isAllowed']);
        Assert::false($this->users->getByEmail($victim)->isAllowed());
    }
}

(new TestUsersPresenter())->run();
