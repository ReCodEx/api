<?php

namespace App\Model\Repository;

use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\AssignmentSolution;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<AssignmentSolutionSubmission>
 */
class AssignmentSolutionSubmissions extends BaseRepository
{

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, AssignmentSolutionSubmission::class);
    }

    /**
     * Find all submissions created in given time interval.
     * @param DateTime|null $since Only submissions created after this date are returned.
     * @param DateTime|null $until Only submissions created before this date are returned.
     * @return AssignmentSolutionSubmission[]
     */
    public function findByCreatedAt(?DateTime $since, ?DateTime $until): array
    {
        return $this->findByDateTimeColumn($since, $until, 'submittedAt');
    }

    /**
     * Retrieve the last submission for given assignment solution.
     * @param AssignmentSolution $solution
     * @param AssignmentSolutionSubmission|null $omitThisSubmission If set, given submission will be ignored
     *  (as if it is not present on the list). That might be useful when looking for new last submission
     *  whilst a submission is being deleted.
     * @return AssignmentSolutionSubmission|null
     */
    public function getLastSubmission(
        AssignmentSolution $solution,
        ?AssignmentSolutionSubmission $omitThisSubmission = null
    ): ?AssignmentSolutionSubmission {
        $submissions = $this->findBy(
            [ "assignmentSolution" => $solution ],
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
