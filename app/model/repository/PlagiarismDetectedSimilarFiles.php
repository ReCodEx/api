<?php

namespace App\Model\Repository;

use App\Model\Entity\PlagiarismDetectedSimilarFile;
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
}
