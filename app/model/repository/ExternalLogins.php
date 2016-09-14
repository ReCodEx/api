<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\ExternalLogin;
use App\Model\Entity\User;
use App\Helpers\ExternalLogin\UserData;

class ExternalLogins extends Nette\Object {

  private $em;
  private $logins;

  public function __construct(EntityManager $em) {
    $this->em = $em;
    $this->logins = $em->getRepository("App\Model\Entity\ExternalLogin");
  }

  /**
   * @param string $authService
   * @param string $externalId
   * @return User|NULL
   */
  public function getUser($authService, $externalId) {
    $login = $this->logins->findOneBy([
      "authService" => $authService,
      "externalId" => $externalId
    ]);

    if ($login) {
      return $login->getUser();
    }

    return NULL;
  }

  public function persist(ExternalLogin $login, $autoFlush = TRUE) {
    $this->em->persist($login);
    if ($autoFlush) {
      $this->flush();
    }
  }

  public function flush() {
    $this->em->flush();
  }

}
