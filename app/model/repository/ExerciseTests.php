<?php

namespace App\Model\Repository;

use App\Model\Entity\ExerciseTest;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<ExerciseTest>
 */
class ExerciseTests extends BaseRepository
{

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, ExerciseTest::class);
    }
}
