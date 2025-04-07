<?php

namespace App\Model\Repository;

use App\Model\Entity\ReferenceSolutionSubmission;
use App\Model\Entity\ReferenceExerciseSolution;
use Doctrine\ORM\EntityManagerInterface;
use DateTime;

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

    /**
     * Retrieve the last submission for given reference solution.
     * @param ReferenceExerciseSolution $solution
     * @param ReferenceSolutionSubmission|null $omitThisSubmission If set, given submission will be ignored
     *  (as if it is not present on the list). That might be useful when looking for new last submission
     *  whilst a submission is being deleted.
     * @return ReferenceSolutionSubmission|null
     */
    public function getLastSubmission(
        ReferenceExerciseSolution $solution,
        ?ReferenceSolutionSubmission $omitThisSubmission = null
    ): ?ReferenceSolutionSubmission {
        $submissions = $this->findBy(
            ["referenceSolution" => $solution],
            [
                "submittedAt" => "DESC",  // make sure the last one submitted is first one in the result
                "id" => "ASC",
            ]
        );

        if ($omitThisSubmission !== null) {
            $omitSubmissionId = $omitThisSubmission->getId();
            $submissions = array_filter(
                $submissions,
                function ($submission) use ($omitSubmissionId) {
                    return $submission->getId() !== $omitSubmissionId;
                }
            );
        }
        return count($submissions) > 0 ? current($submissions) : null;
    }
}
