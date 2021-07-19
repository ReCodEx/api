<?php

namespace App\Model\Repository;

use App\Model\Entity\Group;
use App\Model\Entity\SolutionFile;
use DateTime;
use App\Model\Entity\UploadedFile;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<UploadedFile>
 */
class UploadedFiles extends BaseRepository
{

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, UploadedFile::class);
    }

    /**
     * @param string[] $ids
     * @return UploadedFile[]
     */
    public function findAllById($ids)
    {
        return $this->findBy(["id" => $ids]);
    }

    /**
     * If given file belongs to an exercise assignment, find the group where the exercise was assigned
     * @param UploadedFile $file
     * @return Group|null
     */
    public function findGroupForSolutionFile(UploadedFile $file)
    {
        if (!($file instanceof SolutionFile)) {
            return null;
        }

        $query = $this->em->createQuery("
            SELECT sub
            FROM App\Model\Entity\AssignmentSolution sub
            WHERE IDENTITY(sub.solution) = :solutionId
        ");
        $query->setParameters([ 'solutionId' => $file->getSolution()->getId() ]);

        $result = $query->getResult();
        if (count($result) === 0) {
            return null;
        }

        return current($result)->getAssignment()->getGroup();
    }

    /**
     * If given file belongs to an exercise, find groups to which exercise belongs to.
     * @param UploadedFile $file
     * @return Group[]
     */
    public function findGroupsForReferenceSolutionFile(UploadedFile $file)
    {
        if (!($file instanceof SolutionFile)) {
            return [];
        }

        $query = $this->em->createQuery("
            SELECT ref
            FROM App\Model\Entity\ReferenceExerciseSolution ref
            WHERE IDENTITY(ref.solution) = :solutionId
        ");
        $query->setParameters([ 'solutionId' => $file->getSolution()->getId() ]);

        $result = $query->getResult();
        if (count($result) === 0) {
            return [];
        }

        return current($result)->getExercise()->getGroups()->toArray();
    }

    /**
     * Find uploaded files that are too old and not assigned to an Exercise or Solution
     * @param DateTime $now Current date
     * @param string $threshold Maximum allowed age of uploaded files
     *                          (in a form acceptable by DateTime::modify after prefixing with a "-" sign)
     * @return UploadedFile[]
     */
    public function findUnused(DateTime $now, string $threshold)
    {
        $thresholdDate = clone $now;
        $thresholdDate->modify("-" . $threshold);

        // Note that we must use custom TYPE() function here to get the value of discriminator column.
        // Using INSTANCE OF operator does not work as it matches derived classes as well (not only uploaded files).
        $query = $this->em->createQuery("
            SELECT f
            FROM App\Model\Entity\UploadedFile f
            WHERE TYPE(f) = 'uploadedfile'
            AND f.uploadedAt < :threshold
        ");
        $query->setParameters([ "threshold" => $thresholdDate ]);

        return $query->getResult();
    }
}
