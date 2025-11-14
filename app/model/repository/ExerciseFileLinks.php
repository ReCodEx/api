<?php

namespace App\Model\Repository;

use App\Model\Entity\ExerciseFileLink;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<ExerciseFileLink>
 */
class ExerciseFileLinks extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, ExerciseFileLink::class);
    }
}
