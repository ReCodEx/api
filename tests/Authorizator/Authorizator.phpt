<?php
include __DIR__ . "/../bootstrap.php";

use App\Security\Authorizator;
use App\Security\Policies\IPermissionPolicy;
use App\Security\PolicyRegistry;
use App\Security\Resource;
use Tester\Assert;
use Mockery\Mock;

class TestAuthorizatorBasic extends Tester\TestCase
{
  use MockeryTrait;

  /** @var PolicyRegistry */
  private $policies;

  /** @var Authorizator */
  private $authorizator;

  /** @var Mock|IPermissionPolicy */
  private $groupsPolicy;

  /** @var Mock|IPermissionPolicy */
  private $usersPolicy;

  public function setUp()
  {
    $this->policies = new PolicyRegistry();
    $this->authorizator = new Authorizator(__DIR__ . '/config/basic.neon', $this->policies);
    $this->groupsPolicy = Mockery::mock(MockPolicy::class)->makePartial();
    $this->usersPolicy = Mockery::mock(MockPolicy::class)->makePartial();
    $this->policies->addPolicy("groups", $this->groupsPolicy);
    $this->policies->addPolicy("users", $this->usersPolicy);
  }

  public function testConditionTrue()
  {
    $this->groupsPolicy->shouldReceive("condition1")->withAnyArgs()->andReturn(TRUE);

    Assert::true($this->authorizator->isAllowed(
      new MockIdentity([ 'child' ]),
      'groups',
      'action1',
      [
        'groups' => 'id'
      ]
    ));
  }

  public function testConditionFalse()
  {
    $this->groupsPolicy->shouldReceive("condition1")->withAnyArgs()->andReturn(FALSE);

    Assert::false($this->authorizator->isAllowed(
      new MockIdentity([ 'child' ]),
      'groups',
      'action1',
      [
        'groups' => 'id'
      ]
    ));
  }

  public function testComplexConditionTrue()
  {
    $this->groupsPolicy->shouldReceive("condition1")->withAnyArgs()->andReturn(TRUE);
    $this->usersPolicy->shouldReceive("condition2")->withAnyArgs()->andReturn(TRUE);

    Assert::true($this->authorizator->isAllowed(
      new MockIdentity([ 'parent' ]),
      'users',
      'action2',
      [
        'groups' => 'id',
        'users' => 'id'
      ]
    ));
  }

  public function testComplexConditionFalse()
  {
    $this->groupsPolicy->shouldReceive("condition1")->withAnyArgs()->andReturn(TRUE);
    $this->usersPolicy->shouldReceive("condition2")->withAnyArgs()->andReturn(FALSE);

    Assert::false($this->authorizator->isAllowed(
      new MockIdentity([ 'parent' ]),
      'users',
      'action2',
      [
        'groups' => 'id',
        'users' => 'id'
      ]
    ));
  }
}

$testCase = new TestAuthorizatorBasic();
$testCase->run();