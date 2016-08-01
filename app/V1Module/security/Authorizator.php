<?php

namespace App\Security;

use Nette\Object;
use Nette\Security as NS;

use App\Model\Repository\Permissions;
use App\Model\Repository\Resources;
use App\Model\Repository\Roles;

class Authorizator extends NS\Permission {

  public function __construct(Roles $roles, Resources $resources, Permissions $permissions) {
    foreach ($roles->findLowestLevelRoles() as $lowestLevelRole) {
      $roles = [$lowestLevelRole];
      while (count($roles) > 0) {
        $role = array_pop($roles);
        $this->addRole($role->getId(), $role->getParentRoleId());
        $roles = array_merge($roles, $role->getChildRoles()->toArray());
      }
    }

    foreach ($resources->findAll() as $resource) {
      $this->addResource($resource->getId());
    }

    foreach ($permissions->findAll() as $permission) {
      if ($permission->isAllowed()) {
        $this->allow($permission->getRoleId(), $permission->getResourceId(), $permission->getAction());
      } else {
        $this->deny($permission->getRoleId(), $permission->getResourceId(), $permission->getId());
      }
    }
  }

}
