<?php
namespace App\Security\ACL;

interface IHardwareGroupPermissions {
  function canViewAll(): bool;
}