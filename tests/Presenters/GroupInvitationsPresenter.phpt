<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Model\Entity\Group;
use App\Model\Entity\GroupInvitation;
use App\Model\Repository\Groups;
use App\Model\Repository\GroupInvitations;
use App\V1Module\Presenters\GroupInvitationsPresenter;
use App\Exceptions\ForbiddenRequestException;
use Doctrine\ORM\EntityManagerInterface;
use Tester\Assert;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

/**
 * @testCase
 */
class TestGroupInvitationsPresenter extends Tester\TestCase
{
    /** @var GroupInvitationsPresenter */
    protected $presenter;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var  Nette\DI\Container */
    protected $container;

    /** @var Nette\Security\User */
    private $user;

    /** @var App\Security\AccessManager */
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
        $this->presenter = PresenterTestHelper::createPresenter($this->container, GroupInvitationsPresenter::class);
    }

    protected function tearDown()
    {
        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
        Mockery::close();
    }

    protected function getInvitation($expired = false): GroupInvitation
    {
        Assert::count(2, $this->presenter->groupInvitations->findAll());
        $invitations = array_filter(
            $this->presenter->groupInvitations->findAll(),
            function ($gi) use ($expired) {
                return $gi->hasExpired() === $expired;
            }
        );
        Assert::truthy($invitations);
        return reset($invitations);
    }

    public function testGetInvitation()
    {
        PresenterTestHelper::login($this->container, PresenterTestHelper::STUDENT_GROUP_MEMBER_LOGIN);

        $invitation = $this->getInvitation();
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:GroupInvitations',
            'GET',
            ['action' => 'default', 'id' => $invitation->getId()],
        );

        Assert::count(2, $payload);
        Assert::equal($invitation->getId(), $payload['invitation']->getId());
        Assert::count(3, $payload['groups']);

        $resultGroups = [];
        foreach ($payload['groups'] as $group) {
            $resultGroups[$group['id']] = $group;
        }

        $gid = $payload['invitation']->getGroup()->getId();
        while ($gid) {
            Assert::truthy($resultGroups[$gid]);
            $gid = $resultGroups[$gid]['parentGroupId'];
        }
    }

    public function testAcceptInvitation()
    {
        PresenterTestHelper::login($this->container, PresenterTestHelper::STUDENT_GROUP_MEMBER_LOGIN);
        $user = PresenterTestHelper::getUser($this->container, PresenterTestHelper::STUDENT_GROUP_MEMBER_LOGIN);

        $invitation = $this->getInvitation();
        $group = $invitation->getGroup();
        Assert::false($group->isStudentOf($user));

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:GroupInvitations',
            'GET',
            ['action' => 'accept', 'id' => $invitation->getId()],
        );

        Assert::equal($group->getId(), $payload["id"]);
        Assert::contains($user->getId(), $payload["privateData"]["students"]);
        Assert::true($group->isStudentOf($user));
    }

    public function testAcceptInvitationExpired()
    {
        PresenterTestHelper::login($this->container, PresenterTestHelper::STUDENT_GROUP_MEMBER_LOGIN);
        $user = PresenterTestHelper::getUser($this->container, PresenterTestHelper::STUDENT_GROUP_MEMBER_LOGIN);

        $invitation = $this->getInvitation(true);
        $group = $invitation->getGroup();
        Assert::false($group->isStudentOf($user));

        Assert::exception(function () use ($invitation) {
            PresenterTestHelper::performPresenterRequest(
                $this->presenter,
                'V1:GroupInvitations',
                'GET',
                ['action' => 'accept', 'id' => $invitation->getId()],
            );
        }, ForbiddenRequestException::class);
        Assert::false($group->isStudentOf($user));
    }

    public function testCreateInvitation()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $group = $this->getInvitation()->getGroup();
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:GroupInvitations',
            'POST',
            ['action' => 'create', 'groupId' => $group->getId()],
            ['expireAt' => null, 'note' => 'd2']
        );

        Assert::count(3, $this->presenter->groupInvitations->findAll());
        Assert::equal($group->getId(), $payload->getGroup()->getId());
        Assert::null($payload->getExpireAt());
        Assert::equal("d2", $payload->getNote());
    }

    public function testCreateInvitationUnauthorized()
    {
        PresenterTestHelper::login($this->container, PresenterTestHelper::STUDENT_GROUP_MEMBER_LOGIN);
        $user = PresenterTestHelper::getUser($this->container, PresenterTestHelper::STUDENT_GROUP_MEMBER_LOGIN);

        $group = $this->getInvitation()->getGroup();

        Assert::exception(function () use ($group) {
            PresenterTestHelper::performPresenterRequest(
                $this->presenter,
                'V1:GroupInvitations',
                'POST',
                ['action' => 'create', 'groupId' => $group->getId()],
                ['expireAt' => null, 'note' => 'd2']
            );
        }, ForbiddenRequestException::class);
        Assert::count(2, $this->presenter->groupInvitations->findAll());
    }

    public function testUpdateInvitation()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $invitation = $this->getInvitation(true);
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:GroupInvitations',
            'POST',
            ['action' => 'update', 'id' => $invitation->getId()],
            ['expireAt' => null, 'note' => 'd2']
        );

        Assert::equal($invitation->getId(), $payload->getId());
        Assert::null($payload->getExpireAt());
        Assert::equal("d2", $payload->getNote());
        foreach ($this->presenter->groupInvitations->findAll() as $i) {
            Assert::false($i->hasExpired());
        }
    }

    public function testRemoveInvitation()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $invitation = $this->getInvitation(true);
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:GroupInvitations',
            'DELETE',
            ['action' => 'remove', 'id' => $invitation->getId()]
        );

        Assert::equal("OK", $payload);
        Assert::count(1, $this->presenter->groupInvitations->findAll());
    }

    public function testListInvitations()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $group = $this->getInvitation()->getGroup();
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:GroupInvitations',
            'DELETE',
            ['action' => 'list', 'groupId' => $group->getId()]
        );

        Assert::count(2, $payload);
        $ids = [];
        foreach ($payload as $invitation) {
            $ids[] = $invitation->getId();
            Assert::equal($group->getId(), $invitation->getGroup()->getId());
        }

        Assert::contains($this->getInvitation(false)->getId(), $ids);
        Assert::contains($this->getInvitation(true)->getId(), $ids);
    }
}

$testCase = new TestGroupInvitationsPresenter();
$testCase->run();
