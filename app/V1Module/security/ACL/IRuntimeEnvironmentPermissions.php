<?php
namespace App\Security\ACL;

interface IRuntimeEnvironmentPermissions {
  function canViewAll(): bool;
}