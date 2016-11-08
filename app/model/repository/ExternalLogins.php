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
   * Find external login based on external id
   * @param   string $externalId ID of the user
   * @return  User|NULL
   */
  public function findByExternalId($externalId) {
    return $this->findOneBy([ "externalId" => $externalId ]);
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
