<?php

namespace App\Model\Repository;

use App\Model\Entity\PlagiarismDetectedSimilarFile;
use App\Model\Entity\PlagiarismDetectedSimilarity;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\SolutionFile;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<PlagiarismDetectedSimilarFile>
 */
class PlagiarismDetectedSimilarFiles extends BaseRepository
{

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, PlagiarismDetectedSimilarFile::class);
    }

    /**
     * A specific filtered fetch that retrieves only similar files related to given tested solution
     * and specified similar solution. Used in ACLs to determine, whether a user can see a similar file.
     * @param AssignmentSolution $testedSolution a related similarity detection record must exist
     * @param AssignmentSolution $solution similar solution to be found
     * @param SolutionFile|null $file reference to the similar file to be found
     * @param string|null $fileEntry a file entry or referene to an external source to be found
     * @return PlagiarismDetectedSimilarFile[]
     */
    public function findByTestedAndSimilarSolution(
        AssignmentSolution $testedSolution,
        AssignmentSolution $solution,
        ?SolutionFile $file = null,
        ?string $fileEntry = null
    ): array {
        $qb = $this->createQueryBuilder("f");
        $qb->andWhere("f.solution = :solution")->setParameter("solution", $solution->getId());

        $sub = $qb->getEntityManager()->createQueryBuilder()
            ->select("ds")->from(PlagiarismDetectedSimilarity::class, "ds")
            ->where("ds.testedSolution = :testedSolution")
            ->andWhere("ds.id = f.detectedSimilarity");
        $qb->andWhere($qb->expr()->exists($sub->getDQL()))
            ->setParameter("testedSolution", $testedSolution->getId());

        if ($file) {
            $qb->andWhere("f.solutionFile = :solutionFile")->setParameter("solutionFile", $file->getId());
        }
        if ($fileEntry) {
            $qb->andWhere("f.fileEntry = :entry")->setParameter("entry", $fileEntry);
        }
        return $qb->getQuery()->getResult();
    }
}
