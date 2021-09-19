<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\WrongCredentialsException;
use App\Helpers\EmailVerificationHelper;
use App\Helpers\RegistrationConfig;
use App\Model\Entity\User;
use App\Model\Entity\Group;
use App\Model\Repository\Users;
use App\Model\Repository\Groups;
use App\V1Module\Presenters\RegistrationPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Tester\Assert;

/**
 * @httpCode any
 * @testCase
 */
class TestRegistrationPresenter extends Tester\TestCase
{
    /** @var RegistrationPresenter */
    protected $presenter;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var Nette\Security\User */
    private $user;

    /** @var string */
    private $presenterPath = "V1:Registration";

    /** @var App\Model\Repository\Instances */
    protected $instances;

    /** @var App\Model\Repository\Logins */
    protected $logins;

    /** @var App\Model\Repository\ExternalLogins */
    protected $externalLogins;

    /** @var  Nette\DI\Container */
    protected $container;

    /** @var Users */
    private $users;

    /** @var Groups */
    private $groups;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);
        $this->user = $container->getByType(\Nette\Security\User::class);
        $this->instances = $container->getByType(\App\Model\Repository\Instances::class);
        $this->logins = $container->getByType(\App\Model\Repository\Logins::class);
        $this->users = $container->getByType(\App\Model\Repository\Users::class);
        $this->groups = $container->getByType(\App\Model\Repository\Groups::class);
        $this->externalLogins = $container->getByType(\App\Model\Repository\ExternalLogins::class);
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
        $this->presenter = PresenterTestHelper::createPresenter($this->container, RegistrationPresenter::class);
        $this->presenter->registrationConfig = new RegistrationConfig(
            [
                'enabled' => true,
                'implicitGroupsIds' => []
            ]
        );
    }

    protected function tearDown()
    {
        Mockery::close();

        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
    }

    public function testCreateAccount()
    {
        $email = "email@email.email";
        $firstName = "firstName";
        $lastName = "lastName";
        $password = "password";
        $instances = $this->instances->findAll();
        $instanceId = array_pop($instances)->getId();
        $titlesBeforeName = "titlesBeforeName";
        $titlesAfterName = "titlesAfterName";

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'POST',
            ['action' => 'createAccount'],
            [
                'email' => $email,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'password' => $password,
                'passwordConfirm' => $password,
                'instanceId' => $instanceId,
                'titlesBeforeName' => $titlesBeforeName,
                'titlesAfterName' => $titlesAfterName
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(201, $result['code']);
        Assert::equal(2, count($result['payload']));
        Assert::true(array_key_exists("accessToken", $result["payload"]));
        Assert::true(array_key_exists("user", $result["payload"]));

        // check created user
        $user = $result["payload"]["user"];
        Assert::equal("$titlesBeforeName $firstName $lastName $titlesAfterName", $user["fullName"]);
        Assert::equal($email, $user["privateData"]["email"]);

        // check created login
        $login = $this->logins->findByUserId($user["id"]);
        Assert::equal($user["id"], $login->getUser()->getId());
        Assert::true($login->passwordsMatchOrEmpty($password, $this->presenter->passwordsService));
    }

    public function testCreateAccountWithImplicitGroups()
    {
        $email = "email@email.email";
        $firstName = "firstName";
        $lastName = "lastName";
        $password = "password";
        $instances = $this->instances->findAll();
        $instance = array_pop($instances);
        $instanceId = $instance->getId();
        $titlesBeforeName = "titlesBeforeName";
        $titlesAfterName = "titlesAfterName";
        $groupId = $instance->getGroups()->filter(
            function (Group $group) {
                return !$group->isArchived() && !$group->isOrganizational();
            }
        )->first()->getId();

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'POST',
            ['action' => 'createAccount'],
            [
                'email' => $email,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'password' => $password,
                'passwordConfirm' => $password,
                'instanceId' => $instanceId,
                'titlesBeforeName' => $titlesBeforeName,
                'titlesAfterName' => $titlesAfterName
            ]
        );
        $this->presenter->registrationConfig = new RegistrationConfig(
            [
                'enabled' => true,
                'implicitGroupsIds' => [$groupId]
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(201, $result['code']);
        Assert::equal(2, count($result['payload']));
        Assert::true(array_key_exists("accessToken", $result["payload"]));
        Assert::true(array_key_exists("user", $result["payload"]));

        // check created user
        $user = $result["payload"]["user"];
        Assert::equal("$titlesBeforeName $firstName $lastName $titlesAfterName", $user["fullName"]);
        Assert::equal($email, $user["privateData"]["email"]);

        // check created login
        $login = $this->logins->findByUserId($user["id"]);
        Assert::equal($user["id"], $login->getUser()->getId());
        Assert::true($login->passwordsMatchOrEmpty($password, $this->presenter->passwordsService));

        // check user is member of groups
        $joinedGroups = $this->groups->findFiltered($login->getUser(), $instanceId);
        Assert::count(1, $joinedGroups);
        Assert::equal($groupId, reset($joinedGroups)->getId());
    }

    public function testCreateAccountRegistrationDisabled()
    {
        $email = "email@email.email";
        $firstName = "firstName";
        $lastName = "lastName";
        $password = "password";
        $instanceId = "bla bla bla random string";
        $titlesBeforeName = "titlesBeforeName";
        $titlesAfterName = "titlesAfterName";

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'POST',
            ['action' => 'createAccount'],
            [
                'email' => $email,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'password' => $password,
                'passwordConfirm' => $password,
                'instanceId' => $instanceId,
                'titlesBeforeName' => $titlesBeforeName,
                'titlesAfterName' => $titlesAfterName
            ]
        );
        $this->presenter->registrationConfig = new RegistrationConfig(
            [
                'enabled' => false,
            ]
        );

        Assert::throws(
            function () use ($request) {
                $this->presenter->run($request);
            },
            ForbiddenRequestException::class
        );
    }

    public function testCreateAccountIcorrectInstance()
    {
        $email = "email@email.email";
        $firstName = "firstName";
        $lastName = "lastName";
        $password = "password";
        $instanceId = "bla bla bla random string";
        $titlesBeforeName = "titlesBeforeName";
        $titlesAfterName = "titlesAfterName";

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'POST',
            ['action' => 'createAccount'],
            [
                'email' => $email,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'password' => $password,
                'passwordConfirm' => $password,
                'instanceId' => $instanceId,
                'titlesBeforeName' => $titlesBeforeName,
                'titlesAfterName' => $titlesAfterName
            ]
        );

        Assert::throws(
            function () use ($request) {
                $this->presenter->run($request);
            },
            BadRequestException::class,
            "Bad Request - Instance '$instanceId' does not exist."
        );
    }

    public function testCreateAccountBadConfirmationPassword()
    {
        $email = "email@email.email";
        $firstName = "firstName";
        $lastName = "lastName";
        $password = "password";
        $passwordConfirm = "passwordConfirm";
        $instances = $this->instances->findAll();
        $instanceId = array_pop($instances)->getId();
        $titlesBeforeName = "titlesBeforeName";
        $titlesAfterName = "titlesAfterName";

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'POST',
            ['action' => 'createAccount'],
            [
                'email' => $email,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'password' => $password,
                'passwordConfirm' => $passwordConfirm,
                'instanceId' => $instanceId,
                'titlesBeforeName' => $titlesBeforeName,
                'titlesAfterName' => $titlesAfterName
            ]
        );

        Assert::throws(
            function () use ($request) {
                $this->presenter->run($request);
            },
            WrongCredentialsException::class
        );
    }

    public function testCreateAccountExistingUser()
    {
        $email = PresenterTestHelper::ADMIN_LOGIN;
        $firstName = "firstName";
        $lastName = "lastName";
        $password = "password";
        $instanceId = "bla bla bla random string";
        $titlesBeforeName = "titlesBeforeName";
        $titlesAfterName = "titlesAfterName";

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'POST',
            ['action' => 'createAccount'],
            [
                'email' => $email,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'password' => $password,
                'passwordConfirm' => $password,
                'instanceId' => $instanceId,
                'titlesBeforeName' => $titlesBeforeName,
                'titlesAfterName' => $titlesAfterName
            ]
        );

        Assert::throws(
            function () use ($request) {
                $this->presenter->run($request);
            },
            BadRequestException::class,
            "Bad Request - This email address is already taken."
        );
    }

    public function testValidateRegistrationData()
    {
        $request = new Nette\Application\Request(
            $this->presenterPath,
            'POST',
            ['action' => 'validateRegistrationData'],
            [
                'email' => "totallyFreeEmail@EmailFreeTotally.freeEmailTotally",
                'password' => "totallySecurePasswordWhichIsNot123456"
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::count(2, $result['payload']);

        Assert::true(array_key_exists("usernameIsFree", $result["payload"]));
        Assert::true($result["payload"]["usernameIsFree"]);

        Assert::true(array_key_exists("passwordScore", $result["payload"]));
        Assert::type('int', $result["payload"]["passwordScore"]);
    }
}

$testCase = new TestRegistrationPresenter();
$testCase->run();
