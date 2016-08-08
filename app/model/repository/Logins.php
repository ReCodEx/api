<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Login;

class Logins extends Nette\Object {

  private $em;
  private $logins;

  public function __construct(EntityManager $em) {
    $this->em = $em;
    $this->logins = $em->getRepository("App\Model\Entity\Login");
  }

  public function getUser($username, $password) {
    $login = $this->logins->findOneBy([ "username" => $username ]);
    if ($login) {
      $oldPwdHash = $login->getPasswordHash();
      if ($login->passwordsMatch($password)) {
        if ($login->getPasswordHash() !== $oldPwdHash) {
          $this->persist($login);
          $this->em->flush();
        }

        return $login->getUser();
      }
    }

    return NULL;
  }

  public function persist(Login $login, $autoFlush = TRUE) {
    $this->em->persist($login);
    if ($autoFlush) {
      $this->flush();
    }
  }

  public function flush() {
    $this->em->flush();
  }

}
