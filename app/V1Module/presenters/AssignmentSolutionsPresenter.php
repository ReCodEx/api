<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Exceptions\BadRequestException;
use App\Exceptions\InternalServerException;
use App\Exceptions\InvalidApiArgumentException;
use App\Exceptions\InvalidStateException;
use App\Exceptions\NotFoundException;
use App\Exceptions\NotReadyException;
use App\Helpers\EvaluationLoadingHelper;
use App\Helpers\FileStorageManager;
use App\Helpers\Notifications\PointsChangedEmailsSender;
use App\Helpers\Notifications\SolutionFlagChangedEmailSender;
use App\Helpers\Validators;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Repository\AssignmentSolutionSubmissions;
use App\Model\Repository\SubmissionFailures;
use App\Model\Repository\Users;
use App\Model\Repository\ReviewComments;
use App\Model\View\AssignmentSolutionSubmissionViewFactory;
use App\Model\View\AssignmentSolutionViewFactory;
use App\Model\View\AssignmentViewFactory;
use App\Model\View\GroupViewFactory;
use App\Model\View\SolutionFilesViewFactory;
use App\Exceptions\ForbiddenRequestException;
use App\Security\ACL\IAssignmentSolutionPermissions;
use App\Security\ACL\IUserPermissions;

/**
 * Endpoints for manipulation of assignment solutions
 * @LoggedIn
 */
class AssignmentSolutionsPresenter extends BasePresenter
{
    /**
     * @var FileStorageManager
     * @inject
     */
    public $fileStorage;

    /**
     * @var AssignmentSolutions
     * @inject
     */
    public $assignmentSolutions;

    /**
     * @var AssignmentSolutionSubmissions
     * @inject
     */
    public $assignmentSolutionSubmissions;

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
     * @var SubmissionFailures
     * @inject
     */
    public $submissionFailures;

    /**
     * @var EvaluationLoadingHelper
     * @inject
     */
    public $evaluationLoadingHelper;

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
     * @var SolutionFilesViewFactory
     * @inject
     */
    public $solutionFilesViewFactory;

    /**
     * @var AssignmentSolutionSubmissionViewFactory
     * @inject
     */
    public $assignmentSolutionSubmissionViewFactory;

    /**
     * @var GroupViewFactory
     * @inject
     */
    public $groupViewFactory;

    /**
     * @var PointsChangedEmailsSender
     * @inject
     */
    public $pointsChangedEmailsSender;

    /**
     * @var SolutionFlagChangedEmailSender
     * @inject
     */
    public $solutionFlagChangedEmailSender;

    /**
     * @var ReviewComments
     * @inject
     */
    public $reviewComments;




    /**
     * Get information about solutions.
     * @GET
     * @throws InternalServerException
     */
    #[Path("id", new VUuid(), "Identifier of the solution", required: true)]
    public function actionSolution(string $id)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Update details about the solution (note, etc...)
     * @POST
     * @throws NotFoundException
     * @throws InternalServerException
     */
    #[Post("note", new VString(0, 1024), "A note by the author of the solution")]
    #[Path("id", new VUuid(), "Identifier of the solution", required: true)]
    public function actionUpdateSolution(string $id)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Delete assignment solution with given identification.
     * @DELETE
     * @throws ForbiddenRequestException
     */
    #[Path("id", new VUuid(), "identifier of assignment solution", required: true)]
    public function actionDeleteSolution(string $id)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Get list of all submissions of a solution
     * @GET
     */
    #[Path("id", new VUuid(), "Identifier of the solution", required: true)]
    public function actionSubmissions(string $id)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Get information about the evaluation of a submission
     * @GET
     * @throws NotFoundException
     * @throws InternalServerException
     */
    #[Path("submissionId", new VString(), "Identifier of the submission", required: true)]
    public function actionSubmission(string $submissionId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDeleteSubmission(string $submissionId)
    {
        $submission = $this->assignmentSolutionSubmissions->findOrThrow($submissionId);
        $solution = $submission->getAssignmentSolution();
        if (!$this->assignmentSolutionAcl->canDeleteEvaluation($solution)) {
            throw new ForbiddenRequestException("You cannot delete this evaluation");
        }
        if ($solution->getSubmissions()->count() < 2) {
            throw new BadRequestException("You cannot delete last evaluation of a solution");
        }
    }

    /**
     * Remove the submission permanently
     * @DELETE
     */
    #[Path("submissionId", new VString(), "Identifier of the submission", required: true)]
    public function actionDeleteSubmission(string $submissionId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSetBonusPoints(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        if (!$this->assignmentSolutionAcl->canSetBonusPoints($solution)) {
            throw new ForbiddenRequestException("You cannot change amount of bonus points for this submission");
        }
    }

    /**
     * Set new amount of bonus points for a solution (and optionally points override)
     * Returns array of solution entities that has been changed by this.
     * @POST
     * @throws NotFoundException
     * @throws InvalidApiArgumentException
     * @throws InvalidStateException
     */
    #[Post("bonusPoints", new VInt(), "New amount of bonus points, can be negative number")]
    #[Post(
        "overriddenPoints",
        new VMixed(),
        "Overrides points assigned to solution by the system",
        required: false,
        nullable: true,
    )]
    #[Path("id", new VUuid(), "Identifier of the solution", required: true)]
    public function actionSetBonusPoints(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSetFlag(string $id, string $flag)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);

        $knownBoolFlags = [
            "accepted" => false, // false = flag being set by a teacher
            "reviewRequest" => true, // the author (student) can also set this flag
        ];
        if (!array_key_exists($flag, $knownBoolFlags)) {
            throw new BadRequestException("Trying to set unknown boolean flag '$flag' to the solution");
        }

        if ($knownBoolFlags[$flag]) {
            // weaker test for flags that may be changed by students (owners)
            if (
                !$this->assignmentSolutionAcl->canSetFlagAsStudent($solution)
                && !$this->assignmentSolutionAcl->canSetFlag($solution)
            ) {
                throw new ForbiddenRequestException("You cannot change '$flag' flag for this solution");
            }
        } else {
            if (!$this->assignmentSolutionAcl->canSetFlag($solution)) {
                throw new ForbiddenRequestException("You cannot change '$flag' flag for this solution");
            }
        }
    }

    /**
     * Set flag of the assignment solution.
     * @POST
     * @throws NotFoundException
     * @throws \Nette\Application\AbortException
     * @throws \Exception
     */
    #[Post("value", new VBool(), "True or false which should be set to given flag name", required: true)]
    #[Path("id", new VUuid(), "identifier of the solution", required: true)]
    #[Path("flag", new VString(), "name of the flag which should to be changed", required: true)]
    public function actionSetFlag(string $id, string $flag)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDownloadSolutionArchive(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        if (!$this->assignmentSolutionAcl->canViewDetail($solution)) {
            throw new ForbiddenRequestException("You cannot access archive of solution files");
        }
    }

    /**
     * Download archive containing all solution files for particular solution.
     * @GET
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws \Nette\Application\BadRequestException
     * @throws \Nette\Application\AbortException
     */
    #[Path("id", new VUuid(), "of assignment solution", required: true)]
    public function actionDownloadSolutionArchive(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckFiles(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        if (!$this->assignmentSolutionAcl->canViewDetail($solution)) {
            throw new ForbiddenRequestException("You cannot access the solution files metadata");
        }
    }

    /**
     * Get the list of submitted files of the solution.
     * @GET
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "of assignment solution", required: true)]
    public function actionFiles(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDownloadResultArchive(string $submissionId)
    {
        $submission = $this->assignmentSolutionSubmissions->findOrThrow($submissionId);
        if (!$this->assignmentSolutionAcl->canDownloadResultArchive($submission->getAssignmentSolution())) {
            throw new ForbiddenRequestException("You cannot access the result archive for this submission");
        }
    }

    /**
     * Download result archive from backend for particular submission.
     * @GET
     * @throws NotFoundException
     * @throws InternalServerException
     * @throws \Nette\Application\AbortException
     */
    #[Path("submissionId", new VString(), required: true)]
    public function actionDownloadResultArchive(string $submissionId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckEvaluationScoreConfig(string $submissionId)
    {
        $submission = $this->assignmentSolutionSubmissions->findOrThrow($submissionId);
        $solution = $submission->getAssignmentSolution();
        if (!$this->assignmentSolutionAcl->canViewEvaluation($solution)) {
            throw new ForbiddenRequestException("You cannot access this evaluation");
        }
    }

    /**
     * Get score configuration associated with given submission evaluation
     * @GET
     * @throws NotFoundException
     * @throws InternalServerException
     */
    #[Path("submissionId", new VString(), "Identifier of the submission", required: true)]
    public function actionEvaluationScoreConfig(string $submissionId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckReviewRequests(string $id)
    {
        $user = $this->users->findOrThrow($id);
        if (!$this->userAcl->canListReviewRequests($user)) {
            throw new ForbiddenRequestException("You are not allowed to list all review request of your groups");
        }
    }

    /**
     * Return all solutions with reviewRequest flag that given user might need to review
     * (is admin/supervisor in corresponding groups).
     * Along with that it returns all assignment entities of the corresponding solutions.
     * @GET
     */
    #[Path("id", new VUuid(), "of the user whose solutions with requested reviews are listed", required: true)]
    public function actionReviewRequests(string $id)
    {
        $this->sendSuccessResponse("OK");
    }
}
