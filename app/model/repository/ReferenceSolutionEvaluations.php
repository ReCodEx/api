<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\ReferenceSolutionEvaluation;

class ReferenceSolutionEvaluations extends Nette\Object {

    private $em;
    private $evaluations;

    public function __construct(EntityManager $em) {
        $this->em = $em;
        $this->evaluations = $em->getRepository("App\Model\Entity\ReferenceSolutionEvaluation");
    }

    public function findAll() {
        return $this->evaluations->findAll();
    }

    public function get($id) {
        return $this->evaluations->findOneById($id);
    }

    public function persist(SubmissionEvaluation $evaluation, $autoFlush = TRUE) {
        $this->em->persist($evaluation);
        if ($autoFlush) {
          $this->flush();
        }
    }

    public function flush() {
      $this->em->flush();
    }
}
