<?php

namespace App\Model\Repository;

use App\Model\Entity\ExerciseTest;
use Kdyby\Doctrine\EntityManager;

/**
 * @method ExerciseTest findOrThrow($id)
 */
class ExerciseTests extends BaseRepository
{

    public function __construct(EntityManager $em)
    {
        parent::__construct($em, ExerciseTest::class);
    }
}
