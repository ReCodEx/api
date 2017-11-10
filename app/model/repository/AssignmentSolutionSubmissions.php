<?php

namespace App\Model\Repository;

use App\Model\Entity\AssignmentSolutionSubmission;
use Kdyby\Doctrine\EntityManager;


class AssignmentSolutionSubmissions extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, AssignmentSolutionSubmission::class);
  }

}
