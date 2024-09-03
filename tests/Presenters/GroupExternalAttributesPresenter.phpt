<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Model\Entity\Group;
use App\Model\Entity\GroupInvitation;
use App\Model\Repository\Groups;
use App\Model\Repository\GroupInvitations;
use App\V1Module\Presenters\GroupExternalAttributesPresenter;
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
        $this->presenter = PresenterTestHelper::createPresenter($this->container, GroupExternalAttributesPresenter::class);
    }

    protected function tearDown()
    {
        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
        Mockery::close();
    }

    private function checkFilterResults(array $payload, int $attrCount, int $groupCount)
    {
        Assert::count(2, $payload);
        Assert::true(array_key_exists('attributes', $payload));
        Assert::true(array_key_exists('groups', $payload));
        Assert::count($attrCount, $payload['attributes']);
        Assert::count($groupCount, $payload['groups']);

        $indexedGroups = [];
        foreach ($payload['groups'] as $group) {
            $indexedGroups[$group['id']] = $group['parentGroupId'];
        }

        foreach ($payload['attributes'] as $attribute) {
            $groupId = $attribute->getGroup()->getId();
            while ($groupId) {
                Assert::true(array_key_exists($groupId, $indexedGroups));
                $groupId = $indexedGroups[$groupId];
            }
        }
    }

    public function testGetAttributesSemester()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container, [TokenScope::GROUP_EXTERNAL_ATTRIBUTES]);

        $filter = [[ "key" => "semester", "value" => "summer" ]];
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:GroupExternalAttributes',
            'GET',
            ['action' => 'default', 'filter' => json_encode($filter)],
        );

        $this->checkFilterResults($payload, 1, 3);
        Assert::equal('test', $payload['attributes'][0]->getService());
        Assert::equal('semester', $payload['attributes'][0]->getKey());
        Assert::equal('summer', $payload['attributes'][0]->getValue());
    }

    public function testGetAttributesLecture()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container, [TokenScope::GROUP_EXTERNAL_ATTRIBUTES]);

        $filter = [[ "key" => "lecture", "value" => "demo" ]];
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:GroupExternalAttributes',
            'GET',
            ['action' => 'default', 'filter' => json_encode($filter)],
        );

        $this->checkFilterResults($payload, 1, 2);
        Assert::equal('test', $payload['attributes'][0]->getService());
        Assert::equal('lecture', $payload['attributes'][0]->getKey());
        Assert::equal('demo', $payload['attributes'][0]->getValue());
    }

    public function testGetAttributesMulti()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container, [TokenScope::GROUP_EXTERNAL_ATTRIBUTES]);

        $filter = [
            [ "service" => "test", "key" => "semester", ],
            [ "key" => "lecture", "value" => "demo" ],
        ];
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:GroupExternalAttributes',
            'GET',
            ['action' => 'default', 'filter' => json_encode($filter)],
        );

        $this->checkFilterResults($payload, 3, 4);
        $attrs = [];
        foreach ($payload['attributes'] as $attr) {
            Assert::equal('test', $attr->getService());
            $attrs[] = $attr->getKey() . '=' . $attr->getValue();
        }
        sort($attrs);
        Assert::equal(['lecture=demo', 'semester=summer', 'semester=winter'], $attrs);
    }

    public function testGetAttributesEmpty()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container, [TokenScope::GROUP_EXTERNAL_ATTRIBUTES]);

        $filter = [[ "key" => "lecture", "value" => "sleeping" ]];
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:GroupExternalAttributes',
            'GET',
            ['action' => 'default', 'filter' => json_encode($filter)],
        );

        $this->checkFilterResults($payload, 0, 0);
    }

    public function testGetAttributesEmpty2()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container, [TokenScope::GROUP_EXTERNAL_ATTRIBUTES]);

        $filter = [[ "service" => "3rdparty", "key" => "lecture" ]];
        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            'V1:GroupExternalAttributes',
            'GET',
            ['action' => 'default', 'filter' => json_encode($filter)],
        );

        $this->checkFilterResults($payload, 0, 0);
    }

    public function testGetAttributesFails()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container, [TokenScope::GROUP_EXTERNAL_ATTRIBUTES]);

        $filters = [
            [ "key" => "semester", "value" => "summer" ],
            "semester: summer",
            [[ "semester" => "summer" ]],
            [[ "key" => "semester", "value" => 1 ]],
        ];
        foreach ($filters as $filter) {
            Assert::exception(function () use ($filter) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    'V1:GroupExternalAttributes',
                    'GET',
                    ['action' => 'default', 'filter' => json_encode($filter)],
                );
            }, BadRequestException::class);
        }
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
