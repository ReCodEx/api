<?php

namespace App\Model\Repository;

use App\Model\Entity\SolutionEvaluation;
use Doctrine\ORM\EntityManagerInterface;

class SolutionEvaluations extends BaseRepository
{

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, SolutionEvaluation::class);
    }
}
