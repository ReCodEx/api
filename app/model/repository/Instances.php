<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

class Instances extends Nette\Object {

    private $em;
    private $instances;

    public function __construct(EntityManager $em) {
        $this->em = $em;
        $this->instances = $em->getRepository("App\Model\Entity\Instance");
    }

    public function findAll() {
        return $this->instances->findAll();
    }

    public function get($id) {
        return $this->instances->findOneById($id);
    }

    public function persist(Instances $instance) {
        $this->em->persist($instance);
    }
}
