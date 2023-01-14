<?php

namespace App\Model\Repository;

use App\Model\Entity\PlagiarismDetectionBatch;
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
}
