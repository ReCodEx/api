<?php

namespace App\Model\Repository;

use App\Model\Entity\SubmissionFailure;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<SubmissionFailure>
 */
class SubmissionFailures extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, SubmissionFailure::class);
    }

    /**
     * @return SubmissionFailure[]
     */
    public function findUnresolved(): array
    {
        return $this->findBy(
            [
                "resolvedAt" => null
            ]
        );
    }
}
