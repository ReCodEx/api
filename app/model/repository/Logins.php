<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Login;

class Logins extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, Login::CLASS);
  }

  public function getUser($username, $password) {
    $login = $this->logins->findOneBy([ "username" => $username ]);
    if ($login) {
      $oldPwdHash = $login->getPasswordHash();
      if ($login->passwordsMatch($password)) {
        if ($login->getPasswordHash() !== $oldPwdHash) {
          // the password has been rehashed - persist the information
          $this->persist($login);
          $this->em->flush();
        }

        return $login->getUser();
      }
    }

    return NULL;
  }

}
