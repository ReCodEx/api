<?php

include __DIR__ . "/../bootstrap.php";

use App\Security\Authorizator;
use App\Security\Loader;
use App\Security\PolicyRegistry;
use App\Security\Roles;
use Tester\Assert;

class Resource1
{
}

interface ITestResource1Permissions
{
    function canAction1(Resource1 $resource): bool;

    function canAction2(Resource1 $resource): bool;
}

/**
 * @testCase
 */
class TestAuthorizatorWithScopeRoles extends Tester\TestCase
{
    use MockeryTrait;

    /** @var PolicyRegistry */
    private $policies;

    /** @var Roles */
    private $roles;

    /** @var Authorizator */
    private $authorizator;

    /** @var Loader */
    private $loader;

    public function __construct()
    {
        $this->loader = new Loader(
            TEMP_DIR . '/security', __DIR__ . '/config/with_scope_roles.neon', [
            'resource1' => ITestResource1Permissions::class
        ], Mockery::mock(\App\Security\UserStorage::class)
        );
    }

    public function setUp()
    {
        $this->policies = new PolicyRegistry();
        $this->roles = $this->loader->loadRoles();
        $this->authorizator = $this->loader->loadAuthorizator($this->policies, $this->roles);
    }

    public function testNoScopeRoles()
    {
        Assert::true(
            $this->authorizator->isAllowed(
                new MockIdentity(['normal_role']),
                'resource1',
                'action1',
                [
                    'resource1' => new Resource1(),
                ]
            )
        );
    }

    public function testRestrictedScopeRole()
    {
        Assert::false(
            $this->authorizator->isAllowed(
                new MockIdentity(['normal_role'], ['effective_role_2']),
                'resource1',
                'action1',
                [
                    'resource1' => new Resource1(),
                ]
            )
        );
    }

    public function testRestrictedNormalRole()
    {
        Assert::false(
            $this->authorizator->isAllowed(
                new MockIdentity(['normal_role'], ['effective_role_2']),
                'resource1',
                'action2',
                [
                    'resource1' => new Resource1(),
                ]
            )
        );
    }

    public function testAgreeingRoles()
    {
        Assert::true(
            $this->authorizator->isAllowed(
                new MockIdentity(['normal_role'], ['effective_role_1']),
                'resource1',
                'action1',
                [
                    'resource1' => new Resource1(),
                ]
            )
        );
    }

    public function testBothScopeRoles()
    {
        Assert::true(
            $this->authorizator->isAllowed(
                new MockIdentity(['normal_role'], ['effective_role_2', 'effective_role_1']),
                'resource1',
                'action1',
                [
                    'resource1' => new Resource1(),
                ]
            )
        );
    }
}

(new TestAuthorizatorWithScopeRoles())->run();
