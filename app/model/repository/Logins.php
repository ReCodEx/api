<?php

namespace App\Model\Repository;

use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Login;
use Nette\Security as NS;
use App\Model\Entity\User;

class Logins extends BaseRepository {

  /** @var NS\User */
  private $userSession;

  public function __construct(EntityManager $em, NS\User $user) {
    parent::__construct($em, Login::CLASS);
    $this->userSession = $user;
  }

  /**
   *
   * @return Login|NULL
   */
  public function findCurrent() {
    $id = $this->userSession->id;
    return $this->findOneBy([ "user" => $id ]);
  }

  /**
   *
   * @param string $username
   * @return Login
   * @throws NotFoundException
   */
  public function findByUsernameOrThrow(string $username): Login {
    $login = $this->findOneBy([ "username" => $username ]);
    if (!$login) {
      throw new NotFoundException("Login with username '$username' does not exist.");
    }

    return $login;
  }

  /**
   *
   * @param string $username
   * @param string $password
   * @return User|NULL
   */
  public function getUser(string $username, string $password) {
    $login = $this->findOneBy([ "username" => $username ]);
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
