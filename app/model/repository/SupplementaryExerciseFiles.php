<?php

namespace App\Model\Repository;

use App\Model\Entity\SupplementaryExerciseFile;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @method SupplementaryExerciseFile findOrThrow($id)
 */
class SupplementaryExerciseFiles extends BaseRepository
{

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, SupplementaryExerciseFile::class);
    }

    /**
     * Find supplementary files that are not used in any exercise nor assignment.
     * @return SupplementaryExerciseFile[]
     */
    public function findUnused(): array
    {
        $query = $this->em->createQuery("
            SELECT f FROM App\Model\Entity\SupplementaryExerciseFile f
            WHERE NOT EXISTS
                (SELECT e FROM App\Model\Entity\Exercise e WHERE e MEMBER OF f.exercises AND e.deletedAt IS NULL)
            AND NOT EXISTS
                (SELECT a FROM App\Model\Entity\Assignment a WHERE a MEMBER OF f.assignments AND a.deletedAt IS NULL)
            AND NOT EXISTS
                (SELECT p FROM App\Model\Entity\Pipeline p WHERE p MEMBER OF f.pipelines AND p.deletedAt IS NULL)
        ");

        return $query->getResult();
    }
}
