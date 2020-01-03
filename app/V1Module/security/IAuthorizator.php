<?php

namespace App\Security;

/**
 * Interface for checking permissions on resources.
 * Note that this interface is intentionally different from Nette\Security\IAuthorizator - it accepts an identity
 * instead of a role.
 */
interface IAuthorizator
{
    /**
     * @param Identity $identity
     * @param string $resource
     * @param string $privilege
     * @param string[] $context
     * @return bool
     */
    function isAllowed(Identity $identity, string $resource, string $privilege, array $context): bool;
}
