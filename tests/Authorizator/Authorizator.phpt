<?php
include "../bootstrap.php";

use Tester\Assert;

class TestAuthorizator extends Tester\TestCase
{
  use MockeryTrait;

  /** @var Mockery\Mock | App\Model\Repository\Roles */
  private $roles;

  /** @var Mockery\Mock | App\Model\Repository\Resources */
  private $resources;

  /** @var Mockery\Mock | App\Model\Repository\Permissions */
  private $permissions;

  /** @var Mockery\Mock | App\Security\Authorizator */
  private $authorizator;

  protected function setUp()
  {
    $this->roles = Mockery::mock(App\Model\Repository\Roles::class);
    $this->resources = Mockery::mock(App\Model\Repository\Resources::class);
    $this->permissions = Mockery::mock(App\Model\Repository\Permissions::class);

    $this->authorizator = new \App\Security\Authorizator($this->roles, $this->resources, $this->permissions);
  }

  public function testDenyByDefault()
  {
    list($role, $resource) = $this->setupSimple();
    $this->permissions->shouldReceive("findAll")->andReturn([]);

    Assert::false($this->authorizator->isAllowed($role->getId(), $resource->getId(), "whatever"));
  }

  public function testAllowedAction()
  {
    list($role, $resource) = $this->setupSimple();

    $permission = new App\Model\Entity\Permission();
    $permission->setIsAllowed(true);
    $permission->setResource($resource);
    $permission->setAction("whatever");
    $permission->setRole($role);

    $this->permissions->shouldReceive("findAll")->andReturn([
      $permission
    ]);

    Assert::true($this->authorizator->isAllowed($role->getId(), $resource->getId(), "whatever"));
  }

  public function testDisabledAction()
  {
    list($role, $resource) = $this->setupSimple();

    $permission = new App\Model\Entity\Permission();
    $permission->setIsAllowed(false);
    $permission->setResource($resource);
    $permission->setAction("whatever");
    $permission->setRole($role);

    $this->permissions->shouldReceive("findAll")->andReturn([
      $permission
    ]);

    Assert::false($this->authorizator->isAllowed($role->getId(), $resource->getId(), "whatever"));
  }

  public function testAllowedActionWildcard()
  {
    list($role, $resource) = $this->setupSimple();

    $permission = new App\Model\Entity\Permission();
    $permission->setIsAllowed(true);
    $permission->setResource($resource);
    $permission->setAction(\App\Model\Entity\Permission::ACTION_WILDCARD);
    $permission->setRole($role);

    $this->permissions->shouldReceive("findAll")->andReturn([
      $permission
    ]);

    Assert::true($this->authorizator->isAllowed($role->getId(), $resource->getId(), "whatever"));
  }

  public function testDeniedActionWildcard()
  {
    list($role, $resource) = $this->setupSimple();

    $permission = new App\Model\Entity\Permission();
    $permission->setIsAllowed(false);
    $permission->setResource($resource);
    $permission->setAction(\App\Model\Entity\Permission::ACTION_WILDCARD);
    $permission->setRole($role);

    $this->permissions->shouldReceive("findAll")->andReturn([
      $permission
    ]);

    Assert::false($this->authorizator->isAllowed($role->getId(), $resource->getId(), "whatever"));
  }

  /**
   * A simple setup with one role and one resource
   * @return array
   */
  protected function setupSimple():array
  {
    $role = new \App\Model\Entity\Role();
    $role->setId("roleA");
    $this->roles->shouldReceive("findAll")->andReturn([
      $role
    ]);

    $this->roles->shouldReceive("findLowestLevelRoles")->andReturn([
      $role,
    ]);

    $resource = new App\Model\Entity\Resource("resourceA");
    $this->resources->shouldReceive("findAll")->andReturn([
      $resource
    ]);

    return array($role, $resource);
  }
}

$testCase = new TestAuthorizator();
$testCase->run();