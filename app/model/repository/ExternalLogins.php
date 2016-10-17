<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\ExternalLogin;
use App\Model\Entity\User;

class ExternalLogins extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, ExternalLogin::CLASS);
  }

  /**
   * @param string $authService
   * @param string $externalId
   * @return User|NULL
   */
  public function getUser($authService, $externalId) {
    $login = $this->findOneBy([
      "authService" => $authService,
      "externalId" => $externalId
    ]);

    if ($login) {
      return $login->getUser();
    }

    return NULL;
  }

}
