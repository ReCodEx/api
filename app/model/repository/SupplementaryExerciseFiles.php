<?php

namespace App\Model\Repository;

use Kdyby\Doctrine\EntityManager;
use App\Model\Entity\SupplementaryExerciseFile;

/**
 * @method SupplementaryExerciseFile findOrThrow($id)
 */
class SupplementaryExerciseFiles extends BaseRepository
{

    public function __construct(EntityManager $em)
    {
        parent::__construct($em, SupplementaryExerciseFile::class);
    }
}
