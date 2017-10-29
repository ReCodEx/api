<?php

namespace App\Helpers;

use App\Security\Identity;
use Nette;
use DateTime;
use Nette\Utils\Json;
use Tracy\ILogger;


/**
 * Logs all actions of users into file.
 */
class UserActions {

  const USER_ACTIONS = "user_actions";
  const COLUMNS_GLUE = ",";

  /** @var Nette\Security\User */
  private $user;

  /** @var ILogger */
  private $logger;

  /**
   * UserActions constructor.
   * @param Nette\Security\User $user
   * @param ILogger $logger
   */
  public function __construct(Nette\Security\User $user, ILogger $logger) {
    $this->user = $user;
    $this->logger = $logger;
  }

  /**
   * Log an action carried out right now by the currently logged user.
   * @param string $action Action name
   * @param array $params Parameters of the request
   * @param int $code HTTP response code
   * @param mixed $data Additional data
   * @return bool
   */
  public function log(string $action, array $params, int $code, $data = null): bool {
    /** @var Identity $identity */
    $identity = $this->user->getIdentity();
    if ($identity === null || !($identity instanceof Identity)) {
      return false;
    }

    // construct content for logger
    $contentArr = [];
    $contentArr[] = $identity->getUserData()->getId();
    $contentArr[] = (new DateTime)->getTimestamp();
    $contentArr[] = $action;
    $contentArr[] = "'" . Json::encode($params) . "'";
    $contentArr[] = $code;
    $contentArr[] = "'" . Json::encode($data) . "'";

    $this->logger->log(implode(self::COLUMNS_GLUE, $contentArr), self::USER_ACTIONS);
    return true;
  }

}
