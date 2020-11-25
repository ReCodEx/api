<?php

namespace App\Helpers;

use DateTime;
use DateInterval;
use Nette;
use App\Model\Repository\Users;
use App\Model\Repository\Groups;
use App\Model\Repository\Exercises;
use App\Model\Repository\Assignments;
use App\Model\Repository\Solutions;
use App\Model\Repository\AssignmentSolutionSubmissions;
use App\Model\Repository\ReferenceSolutionSubmissions;
use App\Model\Repository\SubmissionFailures;

/**
 * A structure with recorded global stats.
 */
class GeneralStats
{
    use Nette\SmartObject;

    /**
     * @var int|null
     * Number of user created/registered during the selected period.
     */
    public $createdUsers = null;

    /**
     * @var int|null
     * Total number of users at present time.
     */
    public $totalUsers = null;

    /**
     * @var int|null
     * Number of users who could be deleted as inactive.
     */
    public $inactiveUsers = null;

    /**
     * @var int|null
     * Total number of groups at present time (including archived).
     */
    public $totalGroups = null;

    /**
     * @var int|null
     * Total number of archived groups at present time.
     */
    public $archivedGroups = null;

    /**
     * @var int|null
     * Number of exercises created during the selected period.
     */
    public $createdExercises = null;

    /**
     * @var int|null
     * Total number of exercises at present time.
     */
    public $totalExercises = null;

    /**
     * @var int|null
     * Number of assignments created during the selected period.
     */
    public $createdAssignments = null;

    /**
     * @var int|null
     * Total number of assignments at present time.
     */
    public $totalAssignments = null;

    /**
     * @var int|null
     * Number of student solutions created during the selected period.
     */
    public $createdSolutions = null;

    /**
     * @var int|null
     * Total number of student solutions at present time.
     */
    public $totalSolutions = null;

    /**
     * @var int|null
     * Number of submissions (and resubmissions) during the selected period.
     */
    public $createdSubmissions = null;

    /**
     * @var int|null
     * Number of failed submissions during the selected period.
     */
    public $failedSubmissions = null;
}


/**
 * This helper gathers multiple statistics (typicaly numbers of new entites)
 * over given period of time.
 */
class GeneralStatsHelper
{
    use Nette\SmartObject;

    /** @var string|null */
    private $inactivityThreshold;

    /** @var Users */
    public $users;

    /** @var Groups */
    public $groups;

    /** @var Exercises */
    public $exercises;

    /** @var Assignments */
    public $assignments;

    /** @var Solutions */
    public $solutions;

    /** @var AssignmentSolutionSubmissions */
    public $assignmentSubmissions;

    /** @var ReferenceSolutionSubmissions */
    public $referenceSubmissions;

    /** @var SubmissionFailures */
    public $submissionFailures;

    public function __construct(
        ?string $inactivityThreshold,
        Users $users,
        Groups $groups,
        Exercises $exercises,
        Assignments $assignments,
        Solutions $solutions,
        AssignmentSolutionSubmissions $assignmentSubmissions,
        ReferenceSolutionSubmissions $referenceSubmissions,
        SubmissionFailures $submissionFailures
    ) {
        $this->inactivityThreshold = $inactivityThreshold;
        $this->users = $users;
        $this->groups = $groups;
        $this->exercises = $exercises;
        $this->assignments = $assignments;
        $this->solutions = $solutions;
        $this->assignmentSubmissions = $assignmentSubmissions;
        $this->referenceSubmissions = $referenceSubmissions;
        $this->submissionFailures = $submissionFailures;
    }

    private function getInactiveUsersCount()
    {
        if (!$this->inactivityThreshold) {
            return 0;
        }
        $before = new DateTime();
        $before->sub(DateInterval::createFromDateString($this->inactivityThreshold));
        $beforeSafe = new DateTime(); // safe guard (so we do not delete all users)
        $beforeSafe->sub(DateInterval::createFromDateString('1 month'));
        return count($this->users->findByLastAuthentication($before < $beforeSafe ? $before : $beforeSafe));
    }

    /**
     * Gather statistics and create a result stats object.
     * @param DateTime|null $since The beginning of the selected period (null == big bang event).
     * @param DateTime|null $until The end of selected period (null == present moment).
     */
    public function gatherStats(?DateTime $since, ?DateTime $until = null)
    {
        $res = new GeneralStats();
        $res->createdUsers = count($this->users->findByCreatedAt($since, $until));
        $res->totalUsers = $this->users->getTotalCount();
        $res->inactiveUsers = $this->getInactiveUsersCount();
        $res->totalGroups = $this->groups->getTotalCount();
        $res->archivedGroups = $this->groups->getArchivedCount();
        $res->createdExercises = count($this->exercises->findByCreatedAt($since, $until));
        $res->totalExercises = $this->exercises->getTotalCount();
        $res->createdAssignments = count($this->assignments->findByCreatedAt($since, $until));
        $res->totalAssignments = $this->assignments->getTotalCount();
        $res->createdSolutions = count($this->solutions->findByCreatedAt($since, $until));
        $res->totalSolutions = $this->solutions->getTotalCount();
        $res->createdSubmissions = count($this->assignmentSubmissions->findByCreatedAt($since, $until))
            + count($this->referenceSubmissions->findByCreatedAt($since, $until));
        $res->failedSubmissions = count($this->submissionFailures->findByCreatedAt($since, $until));
        return $res;
    }
}
