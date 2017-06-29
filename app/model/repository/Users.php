<?php

namespace App\Model\Repository;

use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\User;
use App\Exceptions\NotFoundException;

class Users extends BaseSoftDeleteRepository {
  public function __construct(EntityManager $em) {
    parent::__construct($em, User::class);
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
