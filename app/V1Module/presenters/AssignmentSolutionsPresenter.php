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
use App\Helpers\Validators;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Repository\AssignmentSolutionSubmissions;
use App\Model\Repository\SubmissionFailures;
use App\Model\Repository\Users;
use App\Model\View\AssignmentSolutionSubmissionViewFactory;
use App\Model\View\AssignmentSolutionViewFactory;
use App\Exceptions\ForbiddenRequestException;
use App\Responses\GuzzleResponse;
use App\Responses\StorageFileResponse;
use App\Security\ACL\IAssignmentSolutionPermissions;

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
     * @var AssignmentSolutionViewFactory
     * @inject
     */
    public $assignmentSolutionViewFactory;

    /**
     * @var AssignmentSolutionSubmissionViewFactory
     * @inject
     */
    public $assignmentSolutionSubmissionViewFactory;

    /**
     * @var PointsChangedEmailsSender
     * @inject
     */
    public $pointsChangedEmailsSender;


    public function checkSolution(string $id)
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
        $solution = $this->assignmentSolutions->findOrThrow($id);

        // if there is submission, try to evaluate it
        $submission = $solution->getLastSubmission();
        if ($submission) {
            $this->evaluationLoadingHelper->loadEvaluation($submission);
        }

        // fetch data
        $this->sendSuccessResponse(
            $this->assignmentSolutionViewFactory->getSolutionData($solution)
        );
    }

    public function checkUpdateSolution(string $id)
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
        $req = $this->getRequest();
        $solution = $this->assignmentSolutions->findOrThrow($id);
        $solution->setNote($req->getPost("note"));

        $this->assignmentSolutions->flush();
        $this->sendSuccessResponse($this->assignmentSolutionViewFactory->getSolutionData($solution));
    }

    public function checkDeleteSolution(string $id)
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
        $solution = $this->assignmentSolutions->findOrThrow($id);

        // delete files of submissions that will be deleted in cascade
        $submissions = $solution->getSubmissions()->getValues();
        foreach ($submissions as $submission) {
            $this->fileStorage->deleteResultsArchive($submission);
            $this->fileStorage->deleteJobConfig($submission);
        }

        // delete source codes
        $this->fileStorage->deleteSolutionArchive($solution);

        $solution->setLastSubmission(null); // break cyclic dependency, so submissions may be deleted on cascade
        $this->assignmentSolutions->flush();
        $this->assignmentSolutions->remove($solution);

        $this->sendSuccessResponse("OK");
    }

    public function checkSubmissions(string $id)
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
        $solution = $this->assignmentSolutions->findOrThrow($id);

        $submissions = $this->assignmentSolutionAcl->canViewEvaluation($solution)
            ? $solution->getSubmissions()->getValues()
            : [];

        // display only data that the current user can view
        $submissions = array_map(
            function (AssignmentSolutionSubmission $submission) {
                // try to load evaluation if not present
                $this->evaluationLoadingHelper->loadEvaluation($submission);
                return $this->assignmentSolutionSubmissionViewFactory->getSubmissionData($submission);
            },
            $submissions
        );

        $this->sendSuccessResponse($submissions);
    }

    public function checkSubmission(string $submissionId)
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
        $submission = $this->assignmentSolutionSubmissions->findOrThrow($submissionId);

        // try to load evaluation if not present
        $this->evaluationLoadingHelper->loadEvaluation($submission);

        $submissionData = $this->assignmentSolutionSubmissionViewFactory->getSubmissionData($submission);
        $this->sendSuccessResponse($submissionData);
    }

    public function checkDeleteSubmission(string $submissionId)
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
        $submission = $this->assignmentSolutionSubmissions->findOrThrow($submissionId);
        $solution = $submission->getAssignmentSolution();
        $solution->setLastSubmission($this->assignmentSolutionSubmissions->getLastSubmission($solution, $submission));
        $this->assignmentSolutionSubmissions->remove($submission);
        $this->assignmentSolutionSubmissions->flush();
        $this->fileStorage->deleteResultsArchive($submission);
        $this->fileStorage->deleteJobConfig($submission);
        $this->sendSuccessResponse("OK");
    }

    public function checkSetBonusPoints(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        if (!$this->assignmentSolutionAcl->canSetBonusPoints($solution)) {
            throw new ForbiddenRequestException("You cannot change amount of bonus points for this submission");
        }
    }

    /**
     * Set new amount of bonus points for a solution
     * @POST
     * @Param(type="post", name="bonusPoints", validation="numericint", description="New amount of bonus points, can be negative number")
     * @Param(type="post", name="overriddenPoints", required=false, description="Overrides points assigned to solution by the system")
     * @param string $id Identifier of the solution
     * @throws NotFoundException
     * @throws InvalidArgumentException
     * @throws InvalidStateException
     */
    public function actionSetBonusPoints(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        $oldBonusPoints = $solution->getBonusPoints();
        $oldOverridenPoints = $solution->getOverriddenPoints();

        $newBonusPoints = $this->getRequest()->getPost("bonusPoints");
        $overriddenPoints = $this->getRequest()->getPost("overriddenPoints");

        $solution->setBonusPoints($newBonusPoints);

        // TODO: validations 'null|numericint' for overridenPoints cannot be used, because null is converted to empty string,
        // TODO: which immediately breaks stated validation... in the future, this behaviour has to change
        // TODO: lucky third TODO
        if (Validators::isNumericInt($overriddenPoints)) {
            $solution->setOverriddenPoints($overriddenPoints);
        } else {
            if (empty($overriddenPoints)) {
                $solution->setOverriddenPoints(null);
            } else {
                throw new InvalidArgumentException(
                    "overridenPoints",
                    "The value '$overriddenPoints' is not null|numericint"
                );
            }
        }

        if ($oldBonusPoints !== $newBonusPoints || $oldOverridenPoints !== $overriddenPoints) {
            $this->pointsChangedEmailsSender->solutionPointsUpdated($solution);
        }

        $this->assignmentSolutions->flush();
        $this->sendSuccessResponse("OK");
    }

    public function checkSetFlag(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        if (!$this->assignmentSolutionAcl->canSetFlag($solution)) {
            throw new ForbiddenRequestException("You cannot change flags for this solution");
        }
    }

    /**
     * Set flag of the assignment solution.
     * @POST
     * @param string $id identifier of the solution
     * @param string $flag name of the flag which should to be changed
     * @Param(type="post", name="value", required=true, validation=boolean, description="True or false which should be set to given flag name")
     * @throws NotFoundException
     * @throws \Nette\Application\AbortException
     * @throws \Exception
     */
    public function actionSetFlag(string $id, string $flag)
    {
        $req = $this->getRequest();
        $solution = $this->assignmentSolutions->findOrThrow($id);
        if ($solution->getAssignment() === null) {
            throw new NotFoundException("Assignment for solution '$id' was deleted");
        }

        if ($solution->getSolution()->getAuthor() === null) {
            throw new NotFoundException("Author of solution '$id' was deleted");
        }

        // map of boolean flag names with the information about uniqueness
        $knownBoolFlags = [
            "accepted" => true,
            "reviewed" => false
        ];

        if (!array_key_exists($flag, $knownBoolFlags)) {
            throw new BadRequestException("Trying to set unknown boolean flag '$flag' to the solution");
        }

        // handle given flag
        $unique = $knownBoolFlags[$flag];
        $value = filter_var($req->getPost("value"), FILTER_VALIDATE_BOOLEAN);
        // handle unique flags
        if ($unique && $value) {
            // flag has to be set to false for all other solutions of a user
            $assignmentSolutions = $this->assignmentSolutions->findSolutions(
                $solution->getAssignment(),
                $solution->getSolution()->getAuthor()
            );
            foreach ($assignmentSolutions as $assignmentSolution) {
                $assignmentSolution->setFlag($flag, false);
            }
        }
        // handle given flag
        $solution->setFlag($flag, $value);

        // finally flush all changed to the database
        $this->assignmentSolutions->flush();

        // forward to student statistics of group
        $groupOfSolution = $solution->getAssignment()->getGroup();
        if ($groupOfSolution === null) {
            throw new NotFoundException("Group for assignment '$id' was not found");
        }

        $this->forward(
            'Groups:studentsStats',
            $groupOfSolution->getId(),
            $solution->getSolution()->getAuthor()->getId()
        );
    }

    public function checkSetAccepted(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        if (!$this->assignmentSolutionAcl->canSetAccepted($solution)) {
            throw new ForbiddenRequestException("You cannot change accepted flag for this solution");
        }
    }

    /**
     * Set solution of student as accepted, this solution will be then presented as the best one.
     * @DEPRECATED
     * @POST
     * @param string $id identifier of the solution
     * @throws \Nette\Application\AbortException
     * @throws NotFoundException
     */
    public function actionSetAccepted(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        if ($solution->getAssignment() === null) {
            throw new NotFoundException("Assignment for solution '$id' was deleted");
        }

        if ($solution->getSolution()->getAuthor() === null) {
            throw new NotFoundException("Author of solution '$id' was deleted");
        }

        // accepted flag has to be set to false for all other solutions
        $assignmentSolutions = $this->assignmentSolutions->findSolutions(
            $solution->getAssignment(),
            $solution->getSolution()->getAuthor()
        );
        foreach ($assignmentSolutions as $assignmentSolution) {
            $assignmentSolution->setAccepted(false);
        }

        // finally set the right solution as accepted
        $solution->setAccepted(true);
        $this->assignmentSolutions->flush();

        // forward to student statistics of group
        $groupOfSolution = $solution->getAssignment()->getGroup();
        if ($groupOfSolution === null) {
            throw new NotFoundException("Group for assignment '$id' was not found");
        }

        $this->forward(
            'Groups:studentsStats',
            $groupOfSolution->getId(),
            $solution->getSolution()->getAuthor()->getId()
        );
    }

    public function checkUnsetAccepted(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        if (!$this->assignmentSolutionAcl->canSetAccepted($solution)) {
            throw new ForbiddenRequestException("You cannot change accepted flag for this solution");
        }
    }

    /**
     * Set solution of student as unaccepted if it was.
     * @DEPRECATED
     * @DELETE
     * @param string $id identifier of the solution
     * @throws \Nette\Application\AbortException
     * @throws NotFoundException
     */
    public function actionUnsetAccepted(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        if ($solution->getAssignment() === null) {
            throw new NotFoundException("Assignment for solution '$id' was not found");
        }

        if ($solution->getAssignment()->getGroup() === null) {
            throw new NotFoundException("Group for solution '$id' was not found");
        }

        // set accepted flag as false even if it was false
        $solution->setAccepted(false);
        $this->assignmentSolutions->flush();

        // forward to student statistics of group
        $groupOfSolution = $solution->getAssignment()->getGroup();
        $this->forward(
            'Groups:studentsStats',
            $groupOfSolution->getId(),
            $solution->getSolution()->getAuthor()->getId()
        );
    }

    public function checkDownloadSolutionArchive(string $id)
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
        $solution = $this->assignmentSolutions->findOrThrow($id);
        $zipFile = $this->fileStorage->getSolutionFile($solution);
        if (!$zipFile) {
            throw new NotFoundException("Solution archive not found.");
        }
        $this->sendResponse(new StorageFileResponse($zipFile, "solution-{$id}.zip", "application/zip"));
    }

    public function checkDownloadResultArchive(string $submissionId)
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
        $submission = $this->assignmentSolutionSubmissions->findOrThrow($submissionId);
        $this->evaluationLoadingHelper->loadEvaluation($submission);

        if (!$submission->hasEvaluation()) {
            throw new NotReadyException("Submission is not evaluated yet");
        }

        $file = $this->fileStorage->getResultsArchive($submission);
        if (!$file) {
            throw new NotFoundException("Archive for submission '$submissionId' not found in file storage");
        }

        $this->sendResponse(new StorageFileResponse($file, "results-{$submissionId}.zip", "application/zip"));
    }

    public function checkEvaluationScoreConfig(string $submissionId)
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
        $submission = $this->assignmentSolutionSubmissions->findOrThrow($submissionId);
        $this->evaluationLoadingHelper->loadEvaluation($submission);

        $evaluation = $submission->getEvaluation();
        $scoreConfig = $evaluation !== null ? $evaluation->getScoreConfig() : null;
        $this->sendSuccessResponse($scoreConfig);
    }
}
