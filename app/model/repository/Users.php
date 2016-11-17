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
  public function __construct(EntityManager $em) {
    parent::__construct($em, User::CLASS);
  }

  public function getByEmail(string $email) {
    return $this->findOneBy([ "email" => $email ]);
  }

  public function findOrThrow($id): User {
    $user = $this->get($id);
    if (!$user) {
      throw new NotFoundException("User '$id' does not exist.");
    }

    return $user;
  }
}
