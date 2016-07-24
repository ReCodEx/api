<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\Submission;

class Submissions extends Nette\Object {

    private $em;
    private $submissions;

    public function __construct(EntityManager $em) {
        $this->em = $em;
        $this->submissions = $em->getRepository('App\Model\Entity\Submission');
    }

    public function findAll() {
        return $this->submissions->findAll();
    }

    public function findSubmissions(ExerciseAssignment $assignment, string $userId) {
      return $this->submissions->findBy([
        'user' => $userId,
        'exerciseAssignment' => $assignment
      ]);
    }

    public function get($id) {
        return $this->submissions->findOneById($id);
    }

    public function persist(Submission $submission, $autoFlush = TRUE) {
        $this->em->persist($submission);
        if ($autoFlush) {
          $this->flush();
        }
    }

    public function flush() {
      $this->em->flush();
    }
}
