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
        $this->users = $em->getRepository('App\Model\Entity\User');
    }

    public function findAll() {
        return $this->users->findAll();
    }

    public function get($id) {
        return $this->users->findOneById($id);
    }

    public function persist(User $user) {
        // @todo validate the user
        $this->em->persist($user);
    }
}
