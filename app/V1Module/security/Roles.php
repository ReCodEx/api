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

  /**
   * @var array
   * Indices are role names, values holds a list of all parents (from which a role inherits permissions).
   */
  protected $rolesParents = [];


  public abstract function setup();

  protected function addRole(string $role, array $parents) {
    $this->rolesParents[$role] = $parents;
  }

  /**
   * Verify whether given actual role has at least the permissions of minimal requested role.
   * @param string $actualTestedRole
   * @param string $minimalRequestedRole
   */
  public function isInRole(string $actualTestedRole, string $minimalRequestedRole): bool {
    if ($actualTestedRole === $minimalRequestedRole) {
      return true;
    }

    if (array_key_exists($actualTestedRole, $this->rolesParents)) {
      foreach ($this->rolesParents[$actualTestedRole] as $parent) {
        if ($this->isInRole($parent, $minimalRequestedRole)) {
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
    if (array_key_exists($role, $this->rolesParents)) {
      return true;
    }

    return false;
  }

}
