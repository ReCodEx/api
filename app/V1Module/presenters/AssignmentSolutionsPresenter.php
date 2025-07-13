<?php

namespace App\V1Module\Presenters;

use App\Exceptions\BadRequestException;
use App\Exceptions\InternalServerException;
use App\Exceptions\InvalidArgumentException;
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


    public function noncheckSolution(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        if (!$this->assignmentSolutionAcl->canViewDetail($solution)) {
            throw new ForbiddenRequestException("You cannot view details of this solution");
        }
    }

    /**
     * Get information about solutions.
     * @GET
     * @param string $id Identifier of the solution
     * @throws InternalServerException
     */
    public function actionSolution(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckUpdateSolution(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        if (!$this->assignmentSolutionAcl->canUpdate($solution)) {
            throw new ForbiddenRequestException("You cannot update the solution");
        }
    }

    /**
     * Update details about the solution (note, etc...)
     * @POST
     * @Param(type="post", name="note", validation="string:0..1024", description="A note by the author of the solution")
     * @param string $id Identifier of the solution
     * @throws NotFoundException
     * @throws InternalServerException
     */
    public function actionUpdateSolution(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDeleteSolution(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        if (!$this->assignmentSolutionAcl->canDelete($solution)) {
            throw new ForbiddenRequestException("You cannot delete this assignment solution");
        }
    }

    /**
     * Delete assignment solution with given identification.
     * @DELETE
     * @param string $id identifier of assignment solution
     * @throws ForbiddenRequestException
     */
    public function actionDeleteSolution(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSubmissions(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        if (!$this->assignmentSolutionAcl->canViewDetail($solution)) {
            throw new ForbiddenRequestException("You cannot access submissions of this solution");
        }
    }

    /**
     * Get list of all submissions of a solution
     * @GET
     * @param string $id Identifier of the solution
     */
    public function actionSubmissions(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSubmission(string $submissionId)
    {
        $submission = $this->assignmentSolutionSubmissions->findOrThrow($submissionId);
        $solution = $submission->getAssignmentSolution();
        if (!$this->assignmentSolutionAcl->canViewEvaluation($solution)) {
            throw new ForbiddenRequestException("You cannot access this evaluation");
        }
    }

    /**
     * Get information about the evaluation of a submission
     * @GET
     * @param string $submissionId Identifier of the submission
     * @throws NotFoundException
     * @throws InternalServerException
     */
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
     * @param string $submissionId Identifier of the submission
     */
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
     * @Param(type="post", name="bonusPoints", validation="numericint",
     *        description="New amount of bonus points, can be negative number")
     * @Param(type="post", name="overriddenPoints", required=false,
     *        description="Overrides points assigned to solution by the system")
     * @param string $id Identifier of the solution
     * @throws NotFoundException
     * @throws InvalidArgumentException
     * @throws InvalidStateException
     */
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
     * @param string $id identifier of the solution
     * @param string $flag name of the flag which should to be changed
     * @Param(type="post", name="value", required=true, validation=boolean,
     *        description="True or false which should be set to given flag name")
     * @throws NotFoundException
     * @throws \Nette\Application\AbortException
     * @throws \Exception
     */
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
     * @param string $id of assignment solution
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws \Nette\Application\BadRequestException
     * @throws \Nette\Application\AbortException
     */
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
     * @param string $id of assignment solution
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
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
     * @param string $submissionId
     * @throws NotFoundException
     * @throws InternalServerException
     * @throws \Nette\Application\AbortException
     */
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
     * @param string $submissionId Identifier of the submission
     * @throws NotFoundException
     * @throws InternalServerException
     */
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
     * @param string $id of the user whose solutions with requested reviews are listed
     */
    public function actionReviewRequests(string $id)
    {
        $this->sendSuccessResponse("OK");
    }
}
