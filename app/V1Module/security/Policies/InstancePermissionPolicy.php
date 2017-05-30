<?php
namespace App\Security\Policies;

use App\Model\Entity\Instance;
use App\Model\Repository\Instances;
use App\Security\Identity;

class InstancePermissionPolicy implements IPermissionPolicy {
  /** @var Instances */
  private $instances;

  public function __construct(Instances $instances) {
    $this->instances = $instances;
  }

  function getByID($id) {
    return $this->instances->get($id);
  }

  public function isInstanceMember(Identity $identity, Instance $instance) {
    $user = $identity->getUserData();
    return $user ? $user->belongsTo($instance) : FALSE;
  }
}