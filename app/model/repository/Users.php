<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\User;

class Users extends Nette\Object {

  private $em;
  private $users;

  public function __construct(EntityManager $em) {
    $this->em = $em;
    $this->users = $em->getRepository("App\Model\Entity\User");
  }

  public function findAll() {
    return $this->users->findAll();
  }

  public function get($id) {
    return $this->users->findOneById($id);
  }

  public function getByEmail(string $email) {
    return $this->users->findOneBy([ "email" => $email ]);
  }

  public function persist(User $user, $autoFlush = TRUE) {
    $this->em->persist($user);
    if ($autoFlush) {
      $this->flush();
    }
  }

  public function flush() {
    $this->em->flush();
  }

}
