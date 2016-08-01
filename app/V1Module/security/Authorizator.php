<?php

namespace App\Security;

use Nette\Object;
use Nette\Security\Permission;

use App\Model\Repository\Permission;
use App\Model\Repository\Resources;
use App\Model\Repository\Roles;

class Authorizator extends Permission {

  public function __construct(Roles $roles, Resources $resources, Permission $permissions) {
    foreach ($roles->findAll() as $role) {
      $this->addRole($role->getId(), $role->getParentRoleId());
    }

    foreach ($resources->findAll() as $resource) {
      $this->addResource($resource->getId());
    }

    foreach ($permissions->findAll() as $permission) {
      if ($permission->isAllowed()) {
        $this->allow($permission->getRoleId(), $permission->getResourceId(), $permission->getId());
      } else {
        $this->deny($permission->getRoleId(), $permission->getResourceId(), $permission->getId());
      }
    }
  }

}
