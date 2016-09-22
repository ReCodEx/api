<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\User;

class Users extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, User::CLASS);
  }

  public function getByEmail(string $email) {
    return $this->findOneBy([ "email" => $email ]);
  }

}
