<?php
namespace App\Security\Policies;

use App\Helpers\SisHelper;
use App\Model\Entity\ExternalLogin;
use App\Model\Entity\Group;
use App\Model\Repository\ExternalLogins;
use App\Model\Repository\Groups;
use App\Model\Repository\SisGroupBindings;
use App\Security\Identity;

class SisBoundGroupPermissionPolicy implements IPermissionPolicy {
  /** @var SisGroupBindings */
  private $bindings;

  /** @var SisHelper */
  private $sisHelper;

  /** @var ExternalLogins */
  private $externalLogins;

  public function __construct(SisGroupBindings $bindings, SisHelper $sisHelper, ExternalLogins $externalLogins) {
    $this->bindings = $bindings;
    $this->sisHelper = $sisHelper;
    $this->externalLogins = $externalLogins;
  }

  public function getAssociatedClass() {
    return Group::class;
  }

  public function isSisStudent(Identity $identity, Group $group): bool {
    $user = $identity->getUserData();
    if (!$user) {
      return false;
    }

    /** @var ExternalLogin $login */
    $login = $this->externalLogins->findOneBy([
      'user' => $user,
      'authService' => 'cas-uk'
    ]);

    if ($login === null) {
      return false;
    }

    $sisCourses = iterator_to_array($this->sisHelper->getCourses($login->getExternalId()));

    foreach ($this->bindings->findByGroup($group) as $binding) {
      foreach ($sisCourses as $course) {
        if ($course->isOwnerStudent() && $binding->getCode() === $course->getCode()) {
          return true;
        }
      }
    }

    return false;
  }
}
