<?php

namespace App\V1Module\Presenters;

use App\Exceptions\BadRequestException;
use App\Exceptions\InternalServerException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\InvalidStateException;
use App\Exceptions\NotFoundException;
use App\Exceptions\NotReadyException;
use App\Exceptions\ForbiddenRequestException;
use App\Helpers\Notifications\ReviewsEmailsSender;
use App\Helpers\Validators;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\ReviewComment;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Repository\Users;
use App\Model\Repository\ReviewComments;
use App\Model\View\AssignmentSolutionViewFactory;
use App\Security\ACL\IAssignmentSolutionPermissions;
use DateTime;

/**
 * Endpoints for manipulation of assignment solution reviews
 * @LoggedIn
 */
class AssignmentSolutionReviewsPresenter extends BasePresenter
{
    /**
     * @var ReviewComments
     * @inject
     */
    public $reviewComments;

    /**
     * @var AssignmentSolutions
     * @inject
     */
    public $assignmentSolutions;

    /**
     * @var Users
     * @inject
     */
    public $users;

    /**
     * @var IAssignmentSolutionPermissions
     * @inject
     */
    public $assignmentSolutionAcl;

    /**
     * @var AssignmentSolutionViewFactory
     * @inject
     */
    public $assignmentSolutionViewFactory;

    /**
     * @var ReviewsEmailsSender
     * @inject
     */
    public $reviewsEmailSender;


    public function checkDefault(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        if (!$this->assignmentSolutionAcl->canViewReview($solution)) {
            throw new ForbiddenRequestException("You cannot access review of this solution");
        }
    }

    /**
     * Get detail of the solution and a list of review comments.
     * @GET
     * @param string $id identifier of the solution
     * @throws InternalServerException
     */
    public function actionDefault(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        $this->sendSuccessResponse([
            "solution" => $this->assignmentSolutionViewFactory->getSolutionData($solution),
            "reviewComments" => $solution->getReviewComments()->toArray(),
        ]);
    }

    public function checkUpdate(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        if (!$this->assignmentSolutionAcl->canReview($solution)) {
            throw new ForbiddenRequestException("You cannot perform a review of this solution");
        }
    }

    /**
     * Update the state of the review process of the solution.
     * @POST
     * @Param(type="post", name="closed", validation="bool"
     *        description="If true, the review is closed. If false, the review is (re)opened.")
     * @param string $id identifier of the solution
     * @throws InternalServerException
     */
    public function actionUpdate(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        $closed = filter_var($this->getRequest()->getPost("closed"), FILTER_VALIDATE_BOOLEAN);

        if ($solution->getReviewStartedAt() === null) {
            $solution->setReviewStartedAt(new DateTime()); // the review is marked as opened in any case
        }

        if ($solution->getReviewedAt() === null && $closed) {
            // opened -> closed transition
            $solution->setReviewedAt(new DateTime());

            $issues = 0; // issues are duly counter only when the review is closed
            foreach ($solution->getReviewComments() as $comment) {
                if ($comment->isIssue()) {
                    ++$issues;
                }
            }
            $solution->setIssuesCount($issues);

            $this->assignmentSolutions->persist($solution);
            $this->reviewsEmailSender->solutionReviewClosed($solution);
        } elseif ($solution->getReviewedAt() !== null && !$closed) {
            // closed -> opened (reverse) transition
            $reviewedAt = $solution->getReviewedAt();
            $solution->setReviewedAt(null);
            $solution->setIssuesCount(0);

            $this->assignmentSolutions->persist($solution);
            $this->reviewsEmailSender->solutionReviewReopened($solution, $reviewedAt);
        }

        $this->sendSuccessResponse([
            "solution" => $this->assignmentSolutionViewFactory->getSolutionData($solution),
            "reviewComments" => $solution->getReviewComments()->toArray(),
        ]);
    }

    public function checkRemove(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        if (!$this->assignmentSolutionAcl->canReview($solution)) {
            // user must be able at least to alter the reviews
            throw new ForbiddenRequestException("You cannot erase the review for this solution");
        }

        // either a user has the power to erase reviews blithely, or the user can erase each comment individually
        if (!$this->assignmentSolutionAcl->canDeleteReview($solution)) {
            foreach ($solution->getReviewComments() as $comment) {
                if (!$this->assignmentSolutionAcl->canDeleteReviewComment($solution, $comment)) {
                    throw new ForbiddenRequestException(
                        "You cannot erase the review for this solution since you are not allowed to delete some of the comments"
                    );
                }
            }
        }
    }

    /**
     * Update the state of the review process of the solution.
     * @DELETE
     * @param string $id identifier of the solution
     * @throws InternalServerException
     */
    public function actionRemove(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        $closed = $solution->getReviewedAt(); // remember that!

        // erase all comments
        $this->reviewComments->deleteCommentsOfSolution($solution);

        // reset the state in the solution entity
        $solution->setReviewStartedAt(null);
        $solution->setReviewedAt(null);
        $solution->setIssuesCount(0);
        $this->assignmentSolutions->persist($solution);

        if ($closed !== null) {
            // notifications are sent only for closed reviews
            $this->reviewsEmailSender->solutionReviewRemoved($solution, $closed);
        }

        $this->sendSuccessResponse("OK");
    }

    public function checkNewComment(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        if (!$this->assignmentSolutionAcl->canReview($solution)) {
            throw new ForbiddenRequestException("You cannot perform a review of this solution");
        }
    }

    /**
     * Perform validation of file-line code location within a solution, throws an exception on failure.
     * Currently it does not validate the file names against solution files (may change in the future).
     * @throws BadRequestException
     */
    private function verifyCodeLocation(AssignmentSolution $solution, string $file, int $line)
    {
        if (!$file) {
            throw new BadRequestException("The text of the comment must not be empty.");
        }
    }

    /**
     * Create a new comment within a review.
     * @POST
     * @Param(type="post", name="text", validation="string:1..65535", required=true, description="The comment itself.")
     * @Param(type="post", name="file", validation="string:1..256", required=true,
     *        description="Identification of the file to which the comment is related to.")
     * @Param(type="post", name="line", validation="numericint", required=true,
     *        description="Line in the designated file to which the comment is related to.")
     * @Param(type="post", name="issue", validation="bool"
     *        description="Whether the comment is an issue (expected to be resolved by the student)")
     * @Param(type="post", name="suppressNotification", validation="bool"
     *        description="If true, no email notification will be sent (only applies when the review has been closed)")
     * @param string $id identifier of the solution
     * @throws InternalServerException
     */
    public function actionNewComment(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        if ($solution->getReviewStartedAt() === null) {
            // first comment also opens the review
            $solution->setReviewStartedAt(new DateTime());
            $this->assignmentSolutions->persist($solution);
        }

        // get and verify inputs
        $req = $this->getRequest();
        $text = trim($req->getPost("text"));
        if (!$text) {
            throw new BadRequestException("The text of the comment must not be empty.");
        }

        $file = trim($req->getPost("file"));
        $line = $req->getPost("line");
        $this->verifyCodeLocation($solution, $file, $line);
        $issue = filter_var($req->getPost("issue"), FILTER_VALIDATE_BOOLEAN);

        // create the review comment
        $comment = new ReviewComment($solution, $this->getCurrentUser(), $file, $line, $text, $issue);
        $this->reviewComments->persist($comment);

        if ($solution->getReviewedAt() !== null) {
            // review is already closed, this needs special treatement
            if ($issue) {
                $solution->setIssuesCount($solution->getIssuesCount() + 1);
                $this->assignmentSolutions->persist($solution);
            }

            $suppressNotification = filter_var($req->getPost("suppressNotification"), FILTER_VALIDATE_BOOLEAN);
            if (!$suppressNotification) {
                $this->reviewsEmailSender->newReviewComment($solution, $comment);
            }
        }

        $this->sendSuccessResponse($comment);
    }

    public function checkEditComment(string $id, string $commentId)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        $comment = $this->reviewComments->findOrThrow($commentId);
        if (!$this->assignmentSolutionAcl->canEditReviewComment($solution, $comment)) {
            throw new ForbiddenRequestException("You are not allowed to edit this review comment");
        }

        if ($comment->getSolution()->getId() !== $id) {
            throw new BadRequestException("Selected comment does not belong into the review of selected solution");
        }
    }

    /**
     * Update existing comment within a review.
     * @POST
     * @Param(type="post", name="text", validation="string:1..65535", required=true, description="The comment itself.")
     * @Param(type="post", name="issue", validation="bool"
     *        description="Whether the comment is an issue (expected to be resolved by the student)")
     * @Param(type="post", name="suppressNotification", validation="bool"
     *        description="If true, no email notification will be sent (only applies when the review has been closed)")
     * @param string $id identifier of the solution
     * @param string $commentId identifier of the review comment
     * @throws InternalServerException
     */
    public function actionEditComment(string $id, string $commentId)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        $comment = $this->reviewComments->findOrThrow($commentId);

        // get and verify inputs
        $req = $this->getRequest();
        $text = trim($req->getPost("text"));
        if (!$text) {
            throw new BadRequestException("The text of the comment must not be empty.");
        }

        $issue = $req->getPost("issue") !== null ? filter_var($req->getPost("issue"), FILTER_VALIDATE_BOOLEAN) : null;
        $issueChanged = $issue !== null && $comment->isIssue() !== $issue;

        if ($text !== $comment->getText() || $issueChanged) {
            // modification needed
            $oldText = $comment->getText();
            $comment->setText($text);
            if ($issue !== null) {
                $comment->setIssue($issue);
            }
            $this->reviewComments->persist($comment);

            if ($solution->getReviewedAt() !== null) {
                // review is already closed, this needs special treatement
                if ($issueChanged) {
                    $solution->setIssuesCount($solution->getIssuesCount() + ($issue ? 1 : -1));
                    $this->assignmentSolutions->persist($solution);
                }

                $suppressNotification = filter_var($req->getPost("suppressNotification"), FILTER_VALIDATE_BOOLEAN);
                if (!$suppressNotification) {
                    $this->reviewsEmailSender->changedReviewComment($solution, $comment, $oldText, $issueChanged);
                }
            }
        }

        $this->sendSuccessResponse($comment);
    }

    public function checkDeleteComment(string $id, string $commentId)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        $comment = $this->reviewComments->findOrThrow($commentId);
        if (!$this->assignmentSolutionAcl->canDeleteReviewComment($solution, $comment)) {
            throw new ForbiddenRequestException("You are not allowed to delete this review comment");
        }

        if ($comment->getSolution()->getId() !== $id) {
            throw new BadRequestException("Selected comment does not belong into the review of selected solution");
        }
    }

    /**
     * Remove one comment from a review.
     * @DELETE
     * @param string $id identifier of the solution
     * @param string $commentId identifier of the review comment
     */
    public function actionDeleteComment(string $id, string $commentId)
    {
        $comment = $this->reviewComments->findOrThrow($commentId);
        $this->reviewComments->remove($comment);

        $solution = $this->assignmentSolutions->findOrThrow($id);
        if ($solution->getReviewedAt() !== null) {
            // review has been closed, report the modification
            $this->reviewsEmailSender->removedReviewComment($solution, $comment);
        }
        $this->sendSuccessResponse("OK");
    }
}
