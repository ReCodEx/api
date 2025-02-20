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
     * @throws InternalServerException
     */
    #[Path("id", new VString(), "Identifier of the solution", required: true)]
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
     * @throws NotFoundException
     * @throws InternalServerException
     */
    #[Post("note", new VString(0, 1024), "A note by the author of the solution")]
    #[Path("id", new VString(), "Identifier of the solution", required: true)]
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
     * @throws ForbiddenRequestException
     */
    #[Path("id", new VString(), "identifier of assignment solution", required: true)]
    public function actionDeleteSolution(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);

        // delete review (if any)
        $this->reviewComments->deleteCommentsOfSolution($solution);

        // delete files of submissions that will be deleted in cascade
        $submissions = $solution->getSubmissions()->getValues();
        foreach ($submissions as $submission) {
            $this->fileStorage->deleteResultsArchive($submission);
            $this->fileStorage->deleteJobConfig($submission);
        }

        // delete source codes
        $this->fileStorage->deleteSolutionArchive($solution->getSolution());

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
     */
    #[Path("id", new VString(), "Identifier of the solution", required: true)]
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
     * @throws NotFoundException
     * @throws InternalServerException
     */
    #[Path("submissionId", new VString(), "Identifier of the submission", required: true)]
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
     */
    #[Path("submissionId", new VString(), "Identifier of the submission", required: true)]
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
     * Set new amount of bonus points for a solution (and optionally points override)
     * Returns array of solution entities that has been changed by this.
     * @POST
     * @throws NotFoundException
     * @throws InvalidArgumentException
     * @throws InvalidStateException
     */
    #[Post("bonusPoints", new VInt(), "New amount of bonus points, can be negative number")]
    #[Post("overriddenPoints", new VString(), "Overrides points assigned to solution by the system", required: false)]
    #[Path("id", new VString(), "Identifier of the solution", required: true)]
    public function actionSetBonusPoints(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        $assignment = $solution->getAssignment();
        $author = $solution->getSolution()->getAuthor();
        $oldBonusPoints = $solution->getBonusPoints();
        $oldOverridenPoints = $solution->getOverriddenPoints();

        $newBonusPoints = $this->getRequest()->getPost("bonusPoints");
        $overriddenPoints = $this->getRequest()->getPost("overriddenPoints");

        // remember, who was the best in case the new points will change that
        $oldBest = null;
        if ($assignment && ($oldBonusPoints !== $newBonusPoints || $oldOverridenPoints !== $overriddenPoints)) {
            $oldBest = $this->assignmentSolutions->findBestSolution($assignment, $author);
        }

        $solution->setBonusPoints($newBonusPoints);

        // TODO: validations 'null|numericint' for overridenPoints cannot be used, because null is converted to empty
        // TODO: string which immediately breaks stated validation... in the future, this behaviour has to change
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

        $this->assignmentSolutions->flush();

        $changedSolutions = []; // list of changed solutions reported back in payload
        if ($oldBonusPoints !== $newBonusPoints || $oldOverridenPoints !== $overriddenPoints) {
            $this->pointsChangedEmailsSender->solutionPointsUpdated($solution);
            $changedSolutions[] = $this->assignmentSolutionViewFactory->getSolutionData($solution);
            if ($assignment) {
                $best = $this->assignmentSolutions->findBestSolution($assignment, $author);
                if ($best->getId() !== $oldBest->getId()) {
                    // best solution has changed, we need to report this
                    if ($best->getId() !== $id) {
                        $changedSolutions[] = $this->assignmentSolutionViewFactory->getSolutionData($best);
                    }
                    if ($oldBest->getId() !== $id) {
                        $changedSolutions[] = $this->assignmentSolutionViewFactory->getSolutionData($oldBest);
                    }
                }
            }
        }
        $this->sendSuccessResponse($changedSolutions);
    }

    public function checkSetFlag(string $id, string $flag)
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
    #[Path("id", new VString(), "identifier of the solution", required: true)]
    #[Path("flag", new VString(), "name of the flag which should to be changed", required: true)]
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
            "reviewRequest" => true,
        ];

        if (!array_key_exists($flag, $knownBoolFlags)) {
            throw new BadRequestException("Trying to set unknown boolean flag '$flag' to the solution");
        }

        // handle given flag
        $unique = $knownBoolFlags[$flag];
        $value = filter_var($req->getPost("value"), FILTER_VALIDATE_BOOLEAN);
        $oldValue = $solution->getFlag($flag);
        if ($value === $oldValue) {
            $this->sendSuccessResponse(["solutions" => []]);
            return; // nothing changed
        }

        $oldBestSolution = $this->assignmentSolutions->findBestSolution(
            $solution->getAssignment(),
            $solution->getSolution()->getAuthor()
        );

        // handle unique flags
        $resetedSolution = null; // remeber original holder of a unique flag (for an email notification)
        /* @phpstan-ignore-next-line */
        if ($unique && $value) {
            // flag has to be set to false for all other solutions of a user
            $assignmentSolutions = $this->assignmentSolutions->findSolutions(
                $solution->getAssignment(),
                $solution->getSolution()->getAuthor()
            );
            foreach ($assignmentSolutions as $assignmentSolution) {
                if ($assignmentSolution->getFlag($flag)) {
                    $resetedSolution = $assignmentSolution;
                }
                $assignmentSolution->setFlag($flag, false);
            }
        }
        // handle given flag
        $solution->setFlag($flag, $value);

        // finally flush all changed to the database
        $this->assignmentSolutions->flush();
        $this->assignmentSolutions->refresh($oldBestSolution);

        // send notification email
        $notificationMethod = $flag . 'FlagChanged';
        if (method_exists($this->solutionFlagChangedEmailSender, $notificationMethod)) {
            $this->solutionFlagChangedEmailSender->$notificationMethod(
                $this->getCurrentUser(),
                $solution,
                $value,
                $resetedSolution
            );
        }

        // assemble response (all entites and stats that may have changed)
        $assignmentId = $solution->getAssignment()->getId();
        $groupOfSolution = $solution->getAssignment()->getGroup();
        if ($groupOfSolution === null) {
            throw new NotFoundException("Group for assignment '$id' was not found");
        }

        $resSolutions = [ $id => $this->assignmentSolutionViewFactory->getSolutionData($solution) ];
        if ($resetedSolution) {
            $resSolutions[$resetedSolution->getId()] =
                $this->assignmentSolutionViewFactory->getSolutionData($resetedSolution);
        }

        $bestSolution = $this->assignmentSolutions->findBestSolution(
            $solution->getAssignment(),
            $solution->getSolution()->getAuthor()
        );
        if ($oldBestSolution->getId() !== $bestSolution->getId()) {
            // add old and current best solutions as well (since they have changed)
            $resSolutions[$oldBestSolution->getId()] =
                $this->assignmentSolutionViewFactory->getSolutionData($oldBestSolution);
            $resSolutions[$bestSolution->getId()] =
                $this->assignmentSolutionViewFactory->getSolutionData($bestSolution);
        }

        $this->sendSuccessResponse([
            "solutions" => array_values($resSolutions),
            "stats" => $this->groupViewFactory->getStudentsStats(
                $groupOfSolution,
                $solution->getSolution()->getAuthor()
            ),
        ]);
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
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     * @throws \Nette\Application\BadRequestException
     * @throws \Nette\Application\AbortException
     */
    #[Path("id", new VString(), "of assignment solution", required: true)]
    public function actionDownloadSolutionArchive(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        $zipFile = $this->fileStorage->getSolutionFile($solution->getSolution());
        if (!$zipFile) {
            throw new NotFoundException("Solution archive not found.");
        }
        $this->sendStorageFileResponse($zipFile, "solution-{$id}.zip", "application/zip");
    }

    public function checkFiles(string $id)
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
    #[Path("id", new VString(), "of assignment solution", required: true)]
    public function actionFiles(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id)->getSolution();
        $this->sendSuccessResponse($this->solutionFilesViewFactory->getSolutionFilesData($solution));
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
     * @throws NotFoundException
     * @throws InternalServerException
     * @throws \Nette\Application\AbortException
     */
    #[Path("submissionId", new VString(), required: true)]
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

        $this->sendStorageFileResponse($file, "results-{$submissionId}.zip", "application/zip");
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
     * @throws NotFoundException
     * @throws InternalServerException
     */
    #[Path("submissionId", new VString(), "Identifier of the submission", required: true)]
    public function actionEvaluationScoreConfig(string $submissionId)
    {
        $submission = $this->assignmentSolutionSubmissions->findOrThrow($submissionId);
        $this->evaluationLoadingHelper->loadEvaluation($submission);

        $evaluation = $submission->getEvaluation();
        $scoreConfig = $evaluation !== null ? $evaluation->getScoreConfig() : null;
        $this->sendSuccessResponse($scoreConfig);
    }

    public function checkReviewRequests(string $id)
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
    #[Path("id", new VString(), "of the user whose solutions with requested reviews are listed", required: true)]
    public function actionReviewRequests(string $id)
    {
        $user = $this->users->findOrThrow($id);
        $solutions = $this->assignmentSolutions->findReviewRequestSolutionsOfTeacher($user);

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
