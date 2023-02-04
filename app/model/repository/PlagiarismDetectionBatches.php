<?php

namespace App\Model\Repository;

use App\Model\Entity\PlagiarismDetectionBatch;
use App\Model\Entity\PlagiarismDetectedSimilarity;
use App\Model\Entity\AssignmentSolution;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<PlagiarismDetectionBatch>
 */
class PlagiarismDetectionBatches extends BaseRepository
{

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, PlagiarismDetectionBatch::class);
    }

    /**
     * List all batches, possibly filtered by detection tool and solution.
     * @param string|null $detectionTool if set, only batches produced by a particular tool are returned
     * @param AssignmentSolution|null $solution if set, only batches where given solution has detected similarities
     *                                          are returned
     * @return PlagiarismDetectionBatch[]
     */
    public function findByToolAndSolution(?string $detectionTool, ?AssignmentSolution $solution): array
    {
        $qb = $this->createQueryBuilder("b");
        if ($detectionTool) {
            $qb->andWhere("b.detectionTool = :tool")->setParameter("tool", $detectionTool);
        }
        if ($solution) {
            $sub = $qb->getEntityManager()->createQueryBuilder()
                ->select("ds")->from(PlagiarismDetectedSimilarity::class, "ds");
            $sub->andWhere("ds.batch = b.id")
                ->andWhere("ds.testedSolution = :sid")->setParameter("sid", $solution->getId());
            $qb->andWhere($qb->expr()->exists($sub->getDQL()));
        }
        $qb->orderBy("b.createdAt", "DESC");
        return $qb->getQuery()->getResult();
    }
}
