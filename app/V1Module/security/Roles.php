<?php

namespace App\Security;

use Nette;


/**
 * Class used for management of user roles.
 */
class Roles
{
  use Nette\SmartObject;

  public const STUDENT_ROLE = "student";
  public const SUPERVISOR_STUDENT_ROLE = "supervisor-student";
  public const SUPERVISOR_ROLE = "supervisor";
  public const EMPOWERED_SUPERVISOR_ROLE = "empowered-supervisor";
  public const SUPERADMIN_ROLE = "superadmin";

  /**
   * Array containing all above roles for better searching.
   */
  public const ROLES = [
    self::STUDENT_ROLE,
    self::SUPERVISOR_STUDENT_ROLE,
    self::SUPERVISOR_ROLE,
    self::EMPOWERED_SUPERVISOR_ROLE,
    self::SUPERADMIN_ROLE
  ];

  /**
   * Validate given role against available user roles.
   * @param string $role
   * @return bool true if given role is valid
   */
  public static function validateRole(string $role): bool {
    if (in_array($role, self::ROLES)) {
      return true;
    }

    return false;
  }

}
