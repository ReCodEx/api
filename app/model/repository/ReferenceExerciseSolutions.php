<?php

namespace App\Model\Repository;

use App\Model\Entity\ReferenceExerciseSolution;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<ReferenceExerciseSolution>
 */
class ReferenceExerciseSolutions extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, ReferenceExerciseSolution::class);
    }
}
