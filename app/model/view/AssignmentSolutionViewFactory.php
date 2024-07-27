<?php

namespace App\Model\View;

use App\Helpers\PermissionHints;
use App\Model\Repository\Comments;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\Group;
use App\Model\Entity\User;
use App\Security\ACL\IAssignmentSolutionPermissions;
use App\Security\UserStorage;
use App\Exceptions\InternalServerException;

/**
 * Factory for solution views which somehow do not fit into json serialization of entities.
 */
class AssignmentSolutionViewFactory
{
    /**
     * @var IAssignmentSolutionPermissions
     */
    public $assignmentSolutionAcl;

    /**
     * @var Comments
     */
    private $comments;

    /**
     * @var AssignmentSolutions
     */
    private $solutions;

    /**
     * @var UserStorage
     */
    private $userStorage;

    /**
     * @var AssignmentSolutionSubmissionViewFactory
     */
    private $submissionViewFactory;

    public function __construct(
        IAssignmentSolutionPermissions $assignmentSolutionAcl,
        Comments $comments,
        AssignmentSolutions $solutions,
        UserStorage $userStorage,
        AssignmentSolutionSubmissionViewFactory $submissionViewFactory
    ) {
        $this->assignmentSolutionAcl = $assignmentSolutionAcl;
        $this->comments = $comments;
        $this->solutions = $solutions;
        $this->userStorage = $userStorage;
        $this->submissionViewFactory = $submissionViewFactory;
    }

    /**
     * Determine whether given solution is the best solution for the author and corresponding assignment.
     * @param AssignmentSolution $solution
     * @return bool
     * @throws InternalServerException
     */
    private function isBestSolution(AssignmentSolution $solution): bool
    {
        $assignment = $solution->getAssignment();
        if (!$assignment) {
            return false;
        }
        $best = $this->solutions->findBestSolution($assignment, $solution->getSolution()->getAuthor());
        return $best ? $best->getId() === $solution->getId() : false;
    }

    /**
     * Parametrized view.
     * @param AssignmentSolution $solution
     * @param bool|array|null $bestSolutionsHints
     *   If bool value is provided, it holds the `isBestSolution` value already.
     *   If array value is provided, it holds index of best solutions.
     *   Otherwise the view factory determines the `isBestSolution` value on its own.
     * @return array
     * @throws InternalServerException
     */
    public function getSolutionData(AssignmentSolution $solution, $bestSolutionsHints = null)
    {
        // Get permission details
        $canViewResubmissions = $this->assignmentSolutionAcl->canViewResubmissions($solution);

        $lastSubmissionId = $solution->getLastSubmission() ? $solution->getLastSubmission()->getId() : null;
        $lastSubmissionIdArray = $lastSubmissionId ? [$lastSubmissionId] : [];
        $submissions = $canViewResubmissions ? $solution->getSubmissionsIds() : $lastSubmissionIdArray;

        $lastSubmission = !$solution->getLastSubmission() ? null :
            $this->submissionViewFactory->getSubmissionData($solution->getLastSubmission());

        $thread = $this->comments->getThread($solution->getId());
        $user = $this->userStorage->getUserData();
        $threadCommentsCount = ($thread && $user) ? $this->comments->getThreadCommentsCount($thread, $user) : 0;

        if ($bestSolutionsHints !== null) {
            if (is_array($bestSolutionsHints)) {
                $isBestSolution = array_key_exists($solution->getId(), $bestSolutionsHints);
            } else {
                $isBestSolution = (bool)$bestSolutionsHints;
            }
        } else {
            $isBestSolution = $this->isBestSolution($solution);
        }

        if ($solution->getReviewStartedAt() !== null) {
            $review = [
                "startedAt" => $solution->getReviewStartedAt()->getTimestamp(),
                "closedAt" => $solution->getReviewedAt() ? $solution->getReviewedAt()->getTimestamp() : null,
                "issues" => $solution->getIssuesCount(),
            ];
        } else {
            $review = null;
        }

        $result = [
            "id" => $solution->getId(),
            "attemptIndex" => $solution->getAttemptIndex(),
            "note" => $solution->getNote(),
            "assignmentId" => $solution->getAssignment()?->getId(),
            "authorId" => $solution->getSolution()->getAuthorId(),
            "createdAt" => $solution->getSolution()->getCreatedAt()->getTimestamp(),
            "runtimeEnvironmentId" => $solution->getSolution()->getRuntimeEnvironment()->getId(),
            "maxPoints" => $solution->getMaxPoints(),
            "maxPointsEver" => $solution->getAssignment()?->getMaxPointsBeforeFirstDeadline(),
            "pastDeadline" => $solution->isAfterDeadline() ? 2 : ($solution->isAfterFirstDeadline() ? 1 : 0),
            "accepted" => $solution->isAccepted(),
            "reviewRequest" => $solution->isReviewRequested(),
            "review" => $review,
            "isBestSolution" => $isBestSolution,
            "actualPoints" => $solution->getPoints(),
            "bonusPoints" => $solution->getBonusPoints(),
            "overriddenPoints" => $solution->getOverriddenPoints(),
            "lastSubmission" => $lastSubmission,
            "submissions" => $submissions,
            "commentsStats" => $threadCommentsCount ? [
                "count" => $threadCommentsCount,
                "authoredCount" => $this->comments->getAuthoredCommentsCount($thread, $user),
                "last" => $this->comments->getThreadLastComment($thread, $user),
            ] : null,
            "permissionHints" => PermissionHints::get($this->assignmentSolutionAcl, $solution)
        ];

        if ($this->assignmentSolutionAcl->canViewDetectedPlagiarisms($solution)) {
            $result["plagiarism"] = $solution->getPlagiarismBatch() ? $solution->getPlagiarismBatch()->getId() : null;
        }

        return $result;
    }

    /**
     * Parametrized view.
     * @param AssignmentSolution[] $solutions
     * @param bool|array|null $bestSolutionsHints
     *   If bool value is provided, it holds the `isBestSolution` value already for all solutions.
     *   If iterrable value is provided, it holds a list of best solutions
     *   Otherwise the view factory determines the `isBestSolution` value on its own.
     * @return array
     */
    public function getSolutionsData(array $solutions, $bestSolutionsHints = null)
    {
        $hint = null;
        if ($bestSolutionsHints !== null) {
            if (is_bool($bestSolutionsHints)) {
                $hint = $bestSolutionsHints;
            } else {
                $hint = []; // lets build an index (ids are the keys)
                foreach ($bestSolutionsHints as $solution) {
                    $hint[$solution->getId()] = true;
                }
            }
        }

        $solutions = array_map(
            function (AssignmentSolution $solution) use ($hint) {
                return $this->getSolutionData($solution, $hint);
            },
            $solutions
        );
        return array_values($solutions);
    }
}
