<?php
namespace App\Security\Policies;

use App\Helpers\SisCourseRecord;
use App\Model\Repository\ExternalLogins;
use App\Security\Identity;

class SisCoursePermissionPolicy implements IPermissionPolicy {
  function getAssociatedClass() {
    return SisCourseRecord::class;
  }

  /**
   * @var ExternalLogins
   */
  private $externalLogins;

  public function __construct(ExternalLogins $externalLogins) {
    $this->externalLogins = $externalLogins;
  }

  public function isSupervisor(Identity $identity, SisCourseRecord $course): bool {
    $user = $identity->getUserData();
    $sisUser = $this->externalLogins->getUser("cas-uk", $course->getSisUserId());

    if ($user === NULL) {
      return FALSE;
    }

    return $sisUser === $user && $course->isOwnerSupervisor();
  }
}