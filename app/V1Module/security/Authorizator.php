<?php

namespace App\Security;

use Nette\Object;
use Nette\Security as NS;

use App\Model\Repository\Permissions;
use App\Model\Repository\Resources;
use App\Model\Repository\Roles;

class Authorizator implements NS\IAuthorizator {
  /** @var NS\Permission */
  private $acl;

  /** @var Roles */
  private $roles;

  /** @var Resources */
  private $resources;

  /** @var Permissions */
  private $permissions;

  public function __construct(Roles $roles, Resources $resources, Permissions $permissions) {
    $this->roles = $roles;
    $this->resources = $resources;
    $this->permissions = $permissions;
  }

  private function setup() {
    if ($this->acl === null) {
      $this->acl = NS\Permission();

      foreach ($this->roles->findLowestLevelRoles() as $lowestLevelRole) {
        $roles = [$lowestLevelRole];
        while (count($roles) > 0) {
          $role = array_pop($roles);
          $this->acl->addRole($role->getId(), $role->getParentRoleId());
          $roles = array_merge($roles, $role->getChildRoles()->toArray());
        }
      }

      foreach ($this->resources->findAll() as $resource) {
        $this->acl->addResource($resource->getId());
      }

      foreach ($this->permissions->findAll() as $permission) {
        if ($permission->isAllowed()) {
          $this->acl->allow($permission->getRoleId(), $permission->getResourceId(), $permission->getAction());
        } else {
          $this->acl->deny($permission->getRoleId(), $permission->getResourceId(), $permission->getId());
        }
      }
    }
  }

  public function isAllowed($role, $resource, $privilege) {
    $this->setup();
    return $this->acl->isAllowed($role, $resource, $privilege);
  }
}
