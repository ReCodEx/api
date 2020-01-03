<?php

include __DIR__ . "/../bootstrap.php";

use App\Security\Authorizator;
use App\Security\Loader;
use App\Security\PolicyRegistry;
use App\Security\Roles;
use App\Security\UserStorage;
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
class TestAuthorizatorWithEffectiveRole extends Tester\TestCase
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
            TEMP_DIR . '/security', __DIR__ . '/config/with_effective_role.neon', [
            'resource1' => ITestResource1Permissions::class
        ], Mockery::mock(UserStorage::class)
        );
    }

    public function setUp()
    {
        $this->policies = new PolicyRegistry();
        $this->roles = $this->loader->loadRoles();
        $this->authorizator = $this->loader->loadAuthorizator($this->policies, $this->roles);
    }

    public function testNoEffectiveRoles()
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

    public function testRestrictedEffectiveRole()
    {
        Assert::false(
            $this->authorizator->isAllowed(
                new MockIdentity(['normal_role'], [], 'effective_role_2'),
                'resource1',
                'action1',
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
                new MockIdentity(['normal_role'], [], 'effective_role_1'),
                'resource1',
                'action1',
                [
                    'resource1' => new Resource1(),
                ]
            )
        );
    }
}

(new TestAuthorizatorWithEffectiveRole())->run();
