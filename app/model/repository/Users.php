<?php

namespace App\Model\Repository;

use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\User;


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

  /**
   * Search users firstnames and surnames based on given string.
   * @param string|null $search
   * @return User[]
   */
  public function searchByNames(?string $search): array {
    return $this->searchBy(["firstName", "lastName"], $search);
  }

  public function findByRoles(string ...$roles): array {
    return $this->findBy([ "role" => $roles ]);
  }

}
