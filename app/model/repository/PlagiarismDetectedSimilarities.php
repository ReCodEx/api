<?php

namespace App\Model\Repository;

use App\Model\Entity\PlagiarismDetectedSimilarity;
use App\Model\Entity\PlagiarismDetectionBatch;
use App\Model\Entity\AssignmentSolution;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<PlagiarismDetectedSimilarity>
 */
class PlagiarismDetectedSimilarities extends BaseRepository
{

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, PlagiarismDetectedSimilarity::class);
    }

    /**
     * Get all detected similarities for given solution in given batch.
     * @param PlagiarismDetectionBatch $batch
     * @param AssignmentSolution $solution
     * @return PlagiarismDetectedSimilarity[]
     */
    public function getSolutionSimilarities(PlagiarismDetectionBatch $batch, AssignmentSolution $solution): array
    {
        $qb = $this->createQueryBuilder("ds");
        $qb->where("ds.batch = :batch")->andWhere("ds.testedSolution = :solution")
            ->setParameter("batch", $batch->getId())
            ->setParameter("solution", $solution->getId());
        return $qb->getQuery()->getResult();
    }
}
