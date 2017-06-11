<?php
namespace App\Security\ACL;


use App\Model\Entity\Instance;

interface IInstancePermissions {
  function canAddGroup(Instance $instance): bool;
}