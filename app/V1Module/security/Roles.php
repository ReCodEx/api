<?php

namespace App\Security;

/**
 * Class used for management of user roles, which are dynamically loaded from
 * permission configuration file.
 */
abstract class Roles
{
  public const STUDENT_ROLE = "student";
  public const SUPERVISOR_STUDENT_ROLE = "supervisor-student";
  public const SUPERVISOR_ROLE = "supervisor";
  public const EMPOWERED_SUPERVISOR_ROLE = "empowered-supervisor";
  public const SUPERADMIN_ROLE = "superadmin";

  protected $roles = [];


  public abstract function setup();

  protected function addRole($role, $parents) {
    $this->roles[$role] = $parents;
  }

  public function isInRole($target, $role): bool {
    if ($target === $role) {
      return true;
    }

    if (array_key_exists($target, $this->roles)) {
      foreach ($this->roles[$target] as $parent) {
        if ($this->isInRole($parent, $role)) {
          return true;
        }
      }
    }

    return false;
  }

  /**
   * Validate given role against available user roles.
   * @param string $role
   * @return bool true if given role is valid
   */
  public function validateRole(string $role): bool {
    if (in_array($role, array_keys($this->roles))) {
      return true;
    }

    return false;
  }

}
