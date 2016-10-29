<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\UserAction;

class UserActions extends BaseRepository {

  /** @var Users */
  private $users;

  public function __construct(EntityManager $em, Users $users) {
    parent::__construct($em, UserAction::CLASS);
    $this->users = $users;
  }

  /**
   * Log an action carried out right now by the currently logged user.
   * @param string  $action   Action name
   * @param array   $params   Parameters of the request
   * @param int     $code     HTTP response code
   * @param mixed   $data     Additonal data
   * @return UserAction
   */
  public function log(string $action, array $params, int $code, $data = NULL): UserAction {
    $user = $this->users->findCurrentUserOrThrow();
    $log = new UserAction($user, new DateTime, $action, $params, $code, $data);
    $this->persist($log);
    return $log;
  }

}
