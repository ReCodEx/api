<?php

namespace App\Model\Repository;

use App\Model\Entity\AssignmentSolver;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<AssignmentSolver>
 */
class AssignmentSolvers extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, AssignmentSolver::class);
    }
}
