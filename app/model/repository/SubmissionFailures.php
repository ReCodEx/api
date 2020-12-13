<?php

namespace App\Model\Repository;

use App\Model\Entity\SubmissionFailure;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @method SubmissionFailure findOrThrow($id)
 */
class SubmissionFailures extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, SubmissionFailure::class);
    }

    public function findUnresolved()
    {
        return $this->findBy(
            [
                "resolvedAt" => null
            ]
        );
    }
}
