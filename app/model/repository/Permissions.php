<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\Permission;

class Permissions extends Nette\Object {

    private $em;
    private $permissions;

    public function __construct(EntityManager $em) {
        $this->em = $em;
        $this->permissions = $em->getRepository('App\Model\Entity\Permission');
    }

    public function findAll() {
        return $this->permissions->findAll();
    }

    public function get($id) {
        return $this->permissions->findOneById($id);
    }

    public function persist(Permission $permission) {
        $this->em->persist($permission);
    }
}
