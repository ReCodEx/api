<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

class ExerciseAssignments extends Nette\Object {

    private $em;
    private $assignments;

    public function __construct(EntityManager $em) {
        $this->em = $em;
        $this->assignments = $em->getRepository("App\Model\Entity\ExerciseAssignment");
    }

    public function findAll() {
        return $this->assignments->findAll();
    }

    public function get($id) {
        return $this->assignments->findOneById($id);
    }

    public function persist(ExerciseAssignments $assignment) {
        // @todo validate the assignment
        $this->em->persist($assignment);
    }
}
