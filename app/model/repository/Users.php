<?php

namespace App\Model\Repository;

use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\User;
use App\Exceptions\NotFoundException;


/**
 * @method User findOrThrow(string $id)
 */
class Users extends BaseSoftDeleteRepository {
  public function __construct(EntityManager $em) {
    parent::__construct($em, User::class);
  }

  public function getByEmail(string $email): ?User {
    return $this->findOneBy([ "email" => $email ]);
  }

  public function findByRoles(string ...$roles): array {
    return $this->findBy([ "role" => $roles ]);
  }

}
