<?php

namespace App\Security;

use Nette\Utils\Strings;

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


    abstract public function setup();

    protected function addRole(string $role, array $parents)
    {
        $this->rolesParents[$role] = $parents;
    }

    /**
     * Verify whether given actual role has at least the permissions of minimal requested role.
     * In other words, this function is basicaly a check that $actualTestedRole >= $minimalRequestedRole
     * in the terms of role strenghth (more permissive is bigger).
     * @param string $actualTestedRole
     * @param string $minimalRequestedRole
     */
    public function isInRole(string $actualTestedRole, string $minimalRequestedRole): bool
    {
        if ($actualTestedRole === $minimalRequestedRole) {
            return true;
        }

        if ($actualTestedRole === self::SUPERADMIN_ROLE && !Strings::startsWith($minimalRequestedRole, 'scope-')) {
            return true;  // special case -- superadmin takes it all, except for the scopes
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
    public function validateRole(string $role): bool
    {
        if (array_key_exists($role, $this->rolesParents)) {
            return true;
        }

        return false;
    }
}
