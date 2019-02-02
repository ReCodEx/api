<?php

namespace App\Security\ACL;

interface IBrokerPermissions {
  function canViewStats(): bool;
  function canFreeze(): bool;
  function canUnfreeze(): bool;
}
