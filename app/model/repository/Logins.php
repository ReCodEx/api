<?php

namespace App\Model\Repository;

use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\Login;
use Nette\Security as NS;
use App\Model\Entity\User;
use App\Exceptions\NotFoundException;

class Logins extends BaseRepository {

  /** @var NS\User */
  private $userSession;

  public function __construct(EntityManager $em, NS\User $user) {
    parent::__construct($em, Login::class);
    $this->userSession = $user;
  }

  /**
   * Find currently logged in user's login.
   * @return Login|null
   */
  public function findCurrent() {
    if ($this->userSession->isLoggedIn() === false) {
      return null;
    }

    return $this->findByUserId($this->userSession->getId());
  }

  /**
   * Find user's login
   * @param   string $userId ID of the user
   * @return  Login|null
   */
  public function findByUserId($userId) {
    return $this->findOneBy([ "user" => $userId ]);
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
   * @return User|null
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

    return null;
  }

  /**
   * Clear password of given user.
   * @param User $user
   */
  public function clearUserPassword(User $user) {
    $login = $this->findByUserId($user->getId());
    if ($login) {
      $login->clearPassword();
      $this->flush();
    }
  }

}
