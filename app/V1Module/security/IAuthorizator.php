<?php
namespace App\Security;


/**
 * Interface for checking permissions on resources.
 * Note that this interface is intentionally different from Nette\Security\IAuthorizator - it accepts an identity
 * instead of a role.
 */
interface IAuthorizator {
  function isAllowed(Identity $identity, $resource, string $privilege): bool;
}