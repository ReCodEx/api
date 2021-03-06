<?php

namespace App\Model\Repository;

use App\Model\Entity\ReferenceSolutionSubmission;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<ReferenceSolutionSubmission>
 */
class ReferenceSolutionSubmissions extends BaseRepository
{

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, ReferenceSolutionSubmission::class);
    }

    /**
     * Find all submissions created in given time interval.
     * @param DateTime|null $since Only submissions created after this date are returned.
     * @param DateTime|null $until Only submissions created before this date are returned.
     * @return ReferenceSolutionSubmission[]
     */
    public function findByCreatedAt(?DateTime $since, ?DateTime $until): array
    {
        return $this->findByDateTimeColumn($since, $until, 'submittedAt');
    }
}
