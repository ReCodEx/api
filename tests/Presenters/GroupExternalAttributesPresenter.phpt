<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\V1Module\Presenters\GroupExternalAttributesPresenter;
use App\Model\Repository\Users;
use App\Model\Repository\GroupMemberships;
use App\Exceptions\BadRequestException;
use App\Security\TokenScope;
use Doctrine\ORM\EntityManagerInterface;
use Tester\Assert;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

/**
 * @testCase
 */
class TestGroupExternalAttributesPresenter extends Tester\TestCase
{
    /** @var GroupExternalAttributesPresenter */
    protected $presenter;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var  Nette\DI\Container */
    protected $container;

    /** @var Nette\Security\User */
    private $user;

    /** @var Users */
    private $users;

    /** @var GroupMemberships */
    private $groupMemberships;

    /** @var App\Security\AccessManager */
    private $accessManager;


    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);
        $this->user = $container->getByType(\Nette\Security\User::class);
        $this->users = $container->getByType(Users::class);
        $this->groupMemberships = $container->getByType(GroupMemberships::class);
        $this->accessManager = $container->getByType(\App\Security\AccessManager::class);
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
        $this->presenter = PresenterTestHelper::createPresenter($this->container, GroupExternalAttributesPresenter::class);
    }

    protected function tearDown()
    {
        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
        Mockery::close();
    }

    public function testGetGroupsNoUser()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container, [TokenScope::GROUP_EXTERNAL_ATTRIBUTES]);
        $groups = $this->presenter->groups->findBy(['archivedAt' => null]);
        Assert::true(count($groups) > 0);
        $instanceId = $groups[0]->getInstance()->getId();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:GroupExternalAttributes',
            'GET',
            ['action' => 'default', 'instance' => $instanceId, 'service' => 'test'],
        );

        Assert::count(count($groups), $payload);
        $indexedPayload = [];
        foreach ($payload as $group) {
            $indexedPayload[$group['id']] = $group;
        }

        foreach ($groups as $group) {
            Assert::true(array_key_exists($group->getId(), $indexedPayload));
            $groupPayload = $indexedPayload[$group->getId()];

            // check basic group parameters
            Assert::count(count($group->getPrimaryAdmins()), $groupPayload['admins']);
            Assert::equal($group->isOrganizational(), $groupPayload['organizational']);
            Assert::equal($group->isExam(), $groupPayload['exam']);
            Assert::equal($group->isPublic(), $groupPayload['public']);
            Assert::equal($group->isDetaining(), $groupPayload['detaining']);

            // attributes
            Assert::true(array_key_exists('attributes', $groupPayload));
            $testAttributesCount = 0;
            foreach ($group->getExternalAttributes() as $attribute) {
                if ($attribute->getService() !== 'test') {
                    continue; // only 'test' attributes were requested
                }
                ++$testAttributesCount;
                $values = $groupPayload['attributes'][$attribute->getService()][$attribute->getKey()] ?? null;
                Assert::true(in_array($attribute->getValue(), $values));
            }

            $actualCount = 0;
            foreach ($groupPayload['attributes']['test'] ?? [] as $values) {
                $actualCount += count($values);
            }
            Assert::equal($testAttributesCount, $actualCount);

            // user membership
            Assert::null($groupPayload['membership']);
        }
    }

    public function testGetGroupsStudent()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container, [TokenScope::GROUP_EXTERNAL_ATTRIBUTES]);
        $users = $this->users->findBy(['email' => PresenterTestHelper::STUDENT_GROUP_MEMBER_LOGIN]);
        Assert::count(1, $users);
        $user = current($users);

        $groups = $this->presenter->groups->findBy(['archivedAt' => null]);
        Assert::true(count($groups) > 0);
        $instanceId = $groups[0]->getInstance()->getId();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:GroupExternalAttributes',
            'GET',
            ['action' => 'default', 'instance' => $instanceId, 'service' => 'test', 'user' => $user->getId()],
        );

        Assert::count(count($groups), $payload);
        $total = 0;
        foreach ($payload as $group) {
            $memberships = $this->groupMemberships->findBy(['group' => $group['id'], 'user' => $user->getId()]);
            if (empty($memberships)) {
                Assert::null($group['membership']);
            } else {
                Assert::count(1, $memberships);
                Assert::equal(current($memberships)->getType(), $group['membership']);
                ++$total;
            }
        }
        Assert::true($total > 0);
    }

    public function testGetGroupsTeacher()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container, [TokenScope::GROUP_EXTERNAL_ATTRIBUTES]);
        $users = $this->users->findBy(['email' => PresenterTestHelper::GROUP_SUPERVISOR_LOGIN]);
        Assert::count(1, $users);
        $user = current($users);

        $groups = $this->presenter->groups->findBy(['archivedAt' => null]);
        Assert::true(count($groups) > 0);
        $instanceId = $groups[0]->getInstance()->getId();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:GroupExternalAttributes',
            'GET',
            ['action' => 'default', 'instance' => $instanceId, 'service' => 'test', 'user' => $user->getId()],
        );

        Assert::count(count($groups), $payload);
        $total = 0;
        foreach ($payload as $group) {
            $memberships = $this->groupMemberships->findBy(['group' => $group['id'], 'user' => $user->getId()]);
            if (empty($memberships)) {
                Assert::null($group['membership']);
            } else {
                Assert::count(1, $memberships);
                Assert::equal(current($memberships)->getType(), $group['membership']);
                ++$total;
            }
        }
        Assert::true($total > 0);
    }

    public function testGetAttributesAdd()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container, [TokenScope::GROUP_EXTERNAL_ATTRIBUTES]);
        $attributes = $this->presenter->groupExternalAttributes->findAll();
        Assert::count(5, $attributes);
        $attribute = current($attributes);
        $group = $attribute->getGroup();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:GroupExternalAttributes',
            'POST',
            ['action' => 'add', 'groupId' => $group->getId()],
            ['service' => 'service', 'key' => 'key', 'value' => 'value'],
        );

        Assert::equal("OK", $payload);
        $attributes = $this->presenter->groupExternalAttributes->findAll();
        Assert::count(6, $attributes);
        $attributes = array_filter($attributes, function ($a) {
            return $a->getService() === 'service';
        });
        Assert::count(1, $attributes);
        $attribute = current($attributes);
        Assert::equal("key", $attribute->getKey());
        Assert::equal("value", $attribute->getValue());
    }

    public function testGetAttributesRemove()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container, [TokenScope::GROUP_EXTERNAL_ATTRIBUTES]);
        $attributes = $this->presenter->groupExternalAttributes->findAll();
        Assert::count(5, $attributes);
        $attribute = current($attributes);
        $id = $attribute->getId();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:GroupExternalAttributes',
            'DELETE',
            ['action' => 'remove', 'id' => $id],
        );

        Assert::equal("OK", $payload);
        $attributes = $this->presenter->groupExternalAttributes->findAll();
        Assert::count(4, $attributes);
        foreach ($attributes as $attribute) {
            Assert::notEqual($id, $attribute->getId());
        }
    }
}

$testCase = new TestGroupExternalAttributesPresenter();
$testCase->run();
