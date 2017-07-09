<?php
namespace App\Security\Policies;


use App\Model\Entity\Pipeline;
use App\Security\Identity;

class PipelinePermissionPolicy implements IPermissionPolicy {
  public function getAssociatedClass() {
    return Pipeline::class;
  }

  public function isAuthor(Identity $identity, Pipeline $pipeline) {
    $user = $identity->getUserData();
    if ($user === null) {
      return false;
    }

    return $user === $pipeline->getAuthor();
  }

}
