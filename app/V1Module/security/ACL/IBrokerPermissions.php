<?php

namespace App\Security\ACL;

interface IBrokerPermissions {
  function canViewStatus(): bool;
  function canFreeze(): bool;
  function canUnfreeze(): bool;
}
