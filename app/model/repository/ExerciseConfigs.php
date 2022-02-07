<?php

namespace App\Model\Repository;

use App\Model\Entity\ExerciseConfig;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<ExerciseConfig>
 */
class ExerciseConfigs extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, ExerciseConfig::class);
    }
}
