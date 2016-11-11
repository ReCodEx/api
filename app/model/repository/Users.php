<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\User;
use Nette\Security as NS;
use App\Exceptions\NotFoundException;
use App\Exceptions\ForbiddenRequestException;

class Users extends BaseRepository {

  /** @var NS\User */
  private $userSession;

  public function __construct(EntityManager $em, NS\User $user) {
    parent::__construct($em, User::CLASS);
    $this->userSession = $user;
  }

  public function getByEmail(string $email) {
    return $this->findOneBy([ "email" => $email ]);
  }

  public function findCurrentUserOrThrow(): User {
    if (!$this->userSession->isLoggedIn()) {
      throw new ForbiddenRequestException;
    }

    $id = $this->userSession->id;
    return $this->findOrThrow($id);
  }

  public function findCurrentUser(): User {
    if (!$this->userSession->isLoggedIn()) {
      return NULL;
    }

    return $this->get($this->userSession->id);
  }

  public function findOrThrow($id): User {
    $user = $this->get($id);
    if (!$user) {
      throw new NotFoundException("User '$id' does not exist.");
    }

    return $user;
  }


}
