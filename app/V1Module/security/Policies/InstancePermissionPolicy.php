<?php
namespace App\Security\Policies;

use App\Model\Entity\Instance;
use App\Model\Repository\Instances;
use App\Security\Identity;

class InstancePermissionPolicy implements IPermissionPolicy {
  public function getAssociatedClass() {
    return Instance::class;
  }

  public function isMember(Identity $identity, Instance $instance) {
    $user = $identity->getUserData();
    return $user ? $user->belongsTo($instance) : false;
  }
}
