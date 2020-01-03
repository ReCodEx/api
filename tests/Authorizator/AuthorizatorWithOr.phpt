<?php

include __DIR__ . "/../bootstrap.php";

use App\Security\Authorizator;
use App\Security\Loader;
use App\Security\Policies\IPermissionPolicy;
use App\Security\PolicyRegistry;
use App\Security\Roles;
use Tester\Assert;
use Mockery\Mock;

class Resource1
{
}

class Resource2
{
}

interface ITestResource1Permissions
{
    function canAction1(Resource1 $resource): bool;
}

interface ITestResource2Permissions
{
    function canAction2(Resource1 $resource1, Resource2 $resource2): bool;
}

/**
 * @testCase
 */
class TestAuthorizatorWithOr extends Tester\TestCase
{
    use MockeryTrait;

    /** @var PolicyRegistry */
    private $policies;

    /** @var Roles */
    private $roles;

    /** @var Authorizator */
    private $authorizator;

    /** @var Mock|IPermissionPolicy */
    private $policy1;

    /** @var Mock|IPermissionPolicy */
    private $policy2;

    /** @var Loader */
    private $loader;

    public function __construct()
    {
        $this->loader = new Loader(
            TEMP_DIR . '/security', __DIR__ . '/config/with_or.neon', [
            'resource1' => ITestResource1Permissions::class,
            'resource2' => ITestResource2Permissions::class
        ], Mockery::mock(\App\Security\UserStorage::class)
        );
    }

    public function setUp()
    {
        $this->policies = new PolicyRegistry();
        $this->roles = $this->loader->loadRoles();
        $this->authorizator = $this->loader->loadAuthorizator($this->policies, $this->roles);
        $this->policy1 = Mockery::mock(MockPolicy::class)->makePartial();
        $this->policy1->shouldReceive('getAssociatedClass')->withAnyArgs()->andReturn(Resource1::class);
        $this->policy2 = Mockery::mock(MockPolicy::class)->makePartial();
        $this->policy2->shouldReceive('getAssociatedClass')->withAnyArgs()->andReturn(Resource2::class);
        $this->policies->addPolicy($this->policy1);
        $this->policies->addPolicy($this->policy2);
    }

    public function testBothFalse()
    {
        $this->policy1->shouldReceive("condition1")->withAnyArgs()->andReturn(false);
        $this->policy2->shouldReceive("condition2")->withAnyArgs()->andReturn(false);

        Assert::false(
            $this->authorizator->isAllowed(
                new MockIdentity(['parent']),
                'resource2',
                'action2',
                [
                    'resource1' => new Resource1(),
                    'resource2' => new Resource2()
                ]
            )
        );
    }

    public function testFirstTrue()
    {
        $this->policy1->shouldReceive("condition1")->withAnyArgs()->andReturn(true);
        $this->policy2->shouldReceive("condition2")->withAnyArgs()->andReturn(false);

        Assert::true(
            $this->authorizator->isAllowed(
                new MockIdentity(['parent']),
                'resource2',
                'action2',
                [
                    'resource1' => new Resource1(),
                    'resource2' => new Resource2()
                ]
            )
        );
    }

    public function testSecondTrue()
    {
        $this->policy1->shouldReceive("condition1")->withAnyArgs()->andReturn(false);
        $this->policy2->shouldReceive("condition2")->withAnyArgs()->andReturn(true);

        Assert::true(
            $this->authorizator->isAllowed(
                new MockIdentity(['parent']),
                'resource2',
                'action2',
                [
                    'resource1' => new Resource1(),
                    'resource2' => new Resource2()
                ]
            )
        );
    }

    public function testBothTrue()
    {
        $this->policy1->shouldReceive("condition1")->withAnyArgs()->andReturn(true);
        $this->policy2->shouldReceive("condition2")->withAnyArgs()->andReturn(true);

        Assert::true(
            $this->authorizator->isAllowed(
                new MockIdentity(['parent']),
                'resource2',
                'action2',
                [
                    'resource1' => new Resource1(),
                    'resource2' => new Resource2()
                ]
            )
        );
    }
}

$testCase = new TestAuthorizatorWithOr();
$testCase->run();
