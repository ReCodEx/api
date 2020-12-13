<?php

namespace App\Model\Repository;

use App\Model\Entity\ReferenceExerciseSolution;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @method ReferenceExerciseSolution findOrThrow($solutionId)
 * @method ReferenceExerciseSolution|null get($id)
 */
class ReferenceExerciseSolutions extends BaseRepository
{

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, ReferenceExerciseSolution::class);
    }
}
