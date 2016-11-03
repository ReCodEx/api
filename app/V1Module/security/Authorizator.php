<?php

namespace App\Security;

use Nette;
use Nette\Security as NS;

use App\Model\Repository\Permissions;
use App\Model\Entity\Resource;
use App\Model\Entity\Permission;
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

  /** @var array Scopes of the current user */
  private $scopes;

  public function __construct(Roles $roles, Resources $resources, Permissions $permissions) {
    $this->roles = $roles;
    $this->resources = $resources;
    $this->permissions = $permissions;
  }

  private function setup() {
    $this->acl = new NS\Permission();

    $roles = $this->roles->findAll();
    $insertedRoleIds = [];
    while (count($insertedRoleIds) < count($roles)) {
      $insertedRoles = 0;

      foreach ($roles as $role) {
        if ($role->getParentRoleId() === NULL || in_array($role->getParentRoleId(), $insertedRoleIds)) {
          $this->acl->addRole($role->getId(), $role->getParentRoleId());
          $insertedRoleIds[] = $role->getId();
          $insertedRoles += 1;
        }
      }

      if ($insertedRoles === 0) {
        throw new Nette\InvalidStateException("Cycle detected in Role hierarchy");
      }
    }

    foreach ($this->resources->findAll() as $resource) {
      $this->acl->addResource($resource->getId());
    }

    foreach ($this->permissions->findAll() as $permission) {
      if ($permission->getAction() === Permission::ACTION_WILDCARD) {
        $this->acl->{$permission->isAllowed() ? "allow" : "deny"}(
          $permission->getRoleId(),
          $permission->getResourceId()
        );

        continue;
      }

      if ($permission->isAllowed()) {
        $this->acl->allow($permission->getRoleId(), $permission->getResourceId(), $permission->getAction());
      } else {
        $this->acl->deny($permission->getRoleId(), $permission->getResourceId(), $permission->getId());
      }
    }
  }

  public function isAllowed($role, $resource, $privilege) {
    if ($this->acl === null) {
      $this->setup();
    }

    try {
      return $this->acl->isAllowed($role, $resource, $privilege);
    } catch (Nette\InvalidStateException $e) {
      $resourceEntity = $this->resources->get($resource);
      if (!$resourceEntity) {
        // unknown resource - add it to the database so it does not trigger the error again
        $resourceEntity = new Resource($resource);
        $this->resources->persist($resourceEntity);
      }

      return FALSE;
    }
  }

  /**
   * Set scopes for given user.
   * @param NS\User $user   The user
   * @param array   $scopes List of scopes
   * @return void
   */
  public function setScopes(NS\User $user, array $scopes) {
    $this->scopes[$user->getId()] = $scopes;
  }

  /**
   * Is the given user in the specified scope?
   * @param NS\User $user   The user
   * @param string  $scope  Scope
   * @return bool
   */
  public function isInScope(NS\User $user, string $scope): bool {
    return in_array($scope, $this->scopes[$user->getId()]);
  }
}
