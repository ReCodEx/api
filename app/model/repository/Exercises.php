<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

class Exercises extends Nette\Object {

    private $em;
    private $exercises;

    public function __construct(EntityManager $em) {
        $this->em = $em;
        $this->exercises = $em->getRepository('App\Model\Entity\Exercise');
    }

    public function findAll() {
        return $this->exercises->findAll();
    }

    public function get($id) {
        return $this->exercises->findOneById($id);
    }

    public function persist(Exercises $exercise) {
        // @todo validate the exercise
        $this->em->persist($exercise);
    }
}
