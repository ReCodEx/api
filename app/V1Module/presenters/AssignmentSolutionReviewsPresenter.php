<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Type;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VEmail;
use App\Helpers\MetaFormats\Validators\VFloat;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Helpers\MetaFormats\Validators\VUuid;
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
use App\Model\View\AssignmentViewFactory;
use App\Model\View\AssignmentSolutionViewFactory;
use App\Security\ACL\IAssignmentSolutionPermissions;
use App\Security\ACL\IUserPermissions;
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
     * @var IUserPermissions
     * @inject
     */
    public $userAcl;

    /**
     * @var AssignmentViewFactory
     * @inject
     */
    public $assignmentsViewFactory;

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
     * @throws InternalServerException
     */
    #[Path("id", new VString(), "identifier of the solution", required: true)]
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
     * @throws InternalServerException
     */
    #[Post("close", new VBool(), "If true, the review is closed. If false, the review is (re)opened.")]
    #[Path("id", new VString(), "identifier of the solution", required: true)]
    public function actionUpdate(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        $close = filter_var($this->getRequest()->getPost("close"), FILTER_VALIDATE_BOOLEAN);
        $reviewedAt = $solution->getReviewedAt();

        if ($solution->getReviewStartedAt() === null) {
            $solution->setReviewStartedAt(new DateTime()); // the review is marked as opened in any case
        }

        if ($close && $reviewedAt === null) {
            // opened -> closed transition
            $solution->setReviewedAt(new DateTime());

            $issues = 0; // issues are duly counted only when the review is closed
            foreach ($solution->getReviewComments() as $comment) {
                if ($comment->isIssue()) {
                    ++$issues;
                }
            }
            $solution->setIssuesCount($issues);
        } elseif (!$close && $reviewedAt !== null) {
            // closed -> opened (reverse) transition
            $solution->setReviewedAt(null);
            $solution->setIssuesCount(0);
        }

        // make sure modifications are saved first
        $this->assignmentSolutions->persist($solution);

        // handle mail notifications
        if ($close && $reviewedAt === null) {
            $this->reviewsEmailSender->solutionReviewClosed($solution);
        } elseif (!$close && $reviewedAt !== null) {
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
                        "You cannot erase the review since you are not allowed to delete some of the comments"
                    );
                }
            }
        }
    }

    /**
     * Update the state of the review process of the solution.
     * @DELETE
     * @throws InternalServerException
     */
    #[Path("id", new VString(), "identifier of the solution", required: true)]
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
        if (!$this->assignmentSolutionAcl->canAddReviewComment($solution)) {
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
        if ($line < 0) {
            throw new BadRequestException("Invalid line number.");
        }

        if ($file) {
            $entry = null;
            $tokens = explode('#', $file, 2);
            if (count($tokens) > 1) {
                list($file, $entry) = $tokens;
            }

            $exists = $solution->getSolution()->getFiles()->exists(function ($_, $f) use ($file) {
                return $f->getName() === $file;
            });
            if (!$exists) {
                throw new BadRequestException(
                    "No file named '$file' was submitted for given solution -- unable to associate a review comment."
                );
            }

            // TODO - in the future, we might want to check the entry as well
        } elseif ($line !== 0) {
            throw new BadRequestException("Global comment (with no file) must have a line value set to zero.");
        }
    }

    /**
     * Create a new comment within a review.
     * @POST
     * @throws InternalServerException
     */
    #[Post("text", new VString(1, 65535), "The comment itself.", required: true)]
    #[Post(
        "file",
        new VString(0, 256),
        "Identification of the file to which the comment is related to.",
        required: true,
    )]
    #[Post("line", new VInt(), "Line in the designated file to which the comment is related to.", required: true)]
    #[Post(
        "issue",
        new VBool(),
        "Whether the comment is an issue (expected to be resolved by the student)",
        required: false,
    )]
    #[Post(
        "suppressNotification",
        new VBool(),
        "If true, no email notification will be sent (only applies when the review has been closed)",
        required: false,
    )]
    #[Path("id", new VString(), "identifier of the solution", required: true)]
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
     * @throws InternalServerException
     */
    #[Post("text", new VString(1, 65535), "The comment itself.", required: true)]
    #[Post(
        "issue",
        new VBool(),
        "Whether the comment is an issue (expected to be resolved by the student)",
        required: false,
    )]
    #[Post(
        "suppressNotification",
        new VBool(),
        "If true, no email notification will be sent (only applies when the review has been closed)",
        required: false,
    )]
    #[Path("id", new VString(), "identifier of the solution", required: true)]
    #[Path("commentId", new VString(), "identifier of the review comment", required: true)]
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
     */
    #[Path("id", new VString(), "identifier of the solution", required: true)]
    #[Path("commentId", new VString(), "identifier of the review comment", required: true)]
    public function actionDeleteComment(string $id, string $commentId)
    {
        $comment = $this->reviewComments->findOrThrow($commentId);
        $isIssue = $comment->isIssue();
        $this->reviewComments->remove($comment);

        $solution = $this->assignmentSolutions->findOrThrow($id);
        if ($solution->getReviewedAt() !== null) {
            // review is already closed, this needs special treatement
            if ($isIssue) {
                $solution->setIssuesCount($solution->getIssuesCount() - 1);
                $this->assignmentSolutions->persist($solution);
            }
            $this->reviewComments->flush();

            // deletions are always reported (if the review is closed)
            $this->reviewsEmailSender->removedReviewComment($solution, $comment);
        }
        $this->sendSuccessResponse("OK");
    }

    public function checkPending(string $id)
    {
        $user = $this->users->findOrThrow($id);
        if (!$this->userAcl->canListPendingReviews($user)) {
            throw new ForbiddenRequestException("You are not allowed to list pending reviews of given user");
        }
    }

    /**
     * Return all solutions with pending reviews that given user teaches (is admin/supervisor in corresponding groups).
     * Along with that it returns all assignment entities of the corresponding solutions.
     * @GET
     */
    #[Path("id", new VString(), "of the user whose pending reviews are listed", required: true)]
    public function actionPending(string $id)
    {
        $user = $this->users->findOrThrow($id);
        $solutions = $this->assignmentSolutions->findPendingReviewsOfTeacher($user);

        $assignments = [];
        foreach ($solutions as $solution) {
            $assignment = $solution->getAssignment();
            if ($assignment) {
                $assignments[$assignment->getId()] = $assignment;
            }
        }

        $this->sendSuccessResponse([
            'solutions' => $this->assignmentSolutionViewFactory->getSolutionsData($solutions),
            'assignments' => $this->assignmentsViewFactory->getAssignments(array_values($assignments)),
        ]);
    }
}
