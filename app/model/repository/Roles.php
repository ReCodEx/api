<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\Role;

class Roles extends Nette\Object {

    private $em;
    private $roles;

    public function __construct(EntityManager $em) {
        $this->em = $em;
        $this->roles = $em->getRepository("App\Model\Entity\Role");
    }

    public function findAll() {
        return $this->roles->findAll();
    }

    public function findLowestLevelRoles() {
        return $this->roles->findBy([ "parentRole" => NULL ]);
    }

    public function get($id) {
        return $this->roles->findOneById($id);
    }

    public function persist(Role $role) {
        $this->em->persist($role);
    }
}
