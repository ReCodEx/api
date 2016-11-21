<?php

namespace App\Model\Repository;

use App\Security\Identity;
use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\UserAction;

class UserActions extends BaseRepository {

  /** @var Nette\Security\User */
  private $user;

  public function __construct(EntityManager $em, Nette\Security\User $user) {
    parent::__construct($em, UserAction::CLASS);
    $this->user = $user;
  }

  /**
   * Log an action carried out right now by the currently logged user.
   * @param string  $action   Action name
   * @param array   $params   Parameters of the request
   * @param int     $code     HTTP response code
   * @param mixed   $data     Additonal data
   * @return UserAction|NULL
   */
  public function log(string $action, array $params, int $code, $data = NULL) {
    /** @var Identity $identity */
    $identity = $this->user->identity;

    if ($identity === null || !($identity instanceof Identity)) {
      return NULL;
    }

    $log = new UserAction($identity->getUserData(), new DateTime, $action, $params, $code, $data);
    $this->persist($log);
    return $log;
  }

}
