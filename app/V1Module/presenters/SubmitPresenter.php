<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ExerciseCompilationException;
use App\Exceptions\ExerciseCompilationSoftException;
use App\Exceptions\ExerciseConfigException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\ParseException;
use App\Exceptions\SubmissionFailedException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\NotFoundException;
use App\Helpers\FileStorage\FileStorageException;
use App\Helpers\FileStorageManager;
use App\Helpers\EntityMetadata\Solution\SolutionParams;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Helper as ExerciseConfigHelper;
use App\Helpers\FailureHelper;
use App\Helpers\MonitorConfig;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\Solution;
use App\Model\Entity\SolutionFile;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\Assignment;
use App\Helpers\SubmissionHelper;
use App\Helpers\JobConfig\Generator as JobConfigGenerator;
use App\Model\Entity\SubmissionFailure;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\User;
use App\Model\Repository\Assignments;
use App\Model\Repository\AssignmentSolutionSubmissions;
use App\Model\Repository\AsyncJobs;
use App\Model\Repository\SubmissionFailures;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Repository\Solutions;
use App\Model\Repository\UploadedFiles;
use App\Model\Repository\RuntimeEnvironments;
use App\Model\View\AssignmentSolutionViewFactory;
use App\Model\View\AssignmentSolutionSubmissionViewFactory;
use App\Security\ACL\IAssignmentPermissions;
use App\Async\Handler\ResubmitAllAsyncJobHandler;
use Exception;
use Nette\Http\IResponse;

/**
 * Endpoints for submitting an assignment
 * @LoggedIn
 */
class SubmitPresenter extends BasePresenter
{
    /**
     * @var AsyncJobs
     * @inject
     */
    public $asyncJobs;

    /**
     * @var FileStorageManager
     * @inject
     */
    public $fileStorage;

    /**
     * @var Assignments
     * @inject
     */
    public $assignments;

    /**
     * @var AssignmentSolutions
     * @inject
     */
    public $assignmentSolutions;

    /**
     * @var AssignmentSolutionSubmissions
     * @inject
     */
    public $assignmentSubmissions;

    /**
     * @var Solutions
     * @inject
     */
    public $solutions;

    /**
     * @var SubmissionFailures
     * @inject
     */
    public $submissionFailures;

    /**
     * @var UploadedFiles
     * @inject
     */
    public $files;

    /**
     * @var SubmissionHelper
     * @inject
     */
    public $submissionHelper;

    /**
     * @var FailureHelper
     * @inject
     */
    public $failureHelper;

    /**
     * @var MonitorConfig
     * @inject
     */
    public $monitorConfig;

    /**
     * @var RuntimeEnvironments
     * @inject
     */
    public $runtimeEnvironments;

    /**
     * @var IAssignmentPermissions
     * @inject
     */
    public $assignmentAcl;

    /**
     * @var JobConfigGenerator
     * @inject
     */
    public $jobConfigGenerator;

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
     * @var ExerciseConfigHelper
     * @inject
     */
    public $exerciseConfigHelper;

    /**
     * Determine if given user can submit solutions to assignment.
     * @param Assignment $assignment
     * @param User|null $user
     * @return bool
     */
    private function canReceiveSubmissions(Assignment $assignment, User $user = null)
    {
        return $assignment->isVisibleToStudents() &&
            $assignment->getGroup() &&
            $assignment->getGroup()->hasValidLicence() &&
            $user !== null &&
            count(
                $this->assignmentSolutions->findValidSolutions($assignment, $user)
            ) < $assignment->getSubmissionsCountLimit();
    }

    /**
     * Helper function for getting user from id or current one if null.
     * @param string|null $userId
     * @return User
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    private function getUserOrCurrent(?string $userId): User
    {
        return $userId !== null ? $this->users->findOrThrow($userId) : $this->getCurrentUser();
    }

    /**
     * Compute total size of all uploaded files.
     * @param UploadedFile[] $uploadedFiles
     * @return int
     */
    private function getFilesSize(array $uploadedFiles): int
    {
        $size = 0;
        foreach ($uploadedFiles as $uploadedFile) {
            $size += $uploadedFile->getFileSize();
        }
        return $size;
    }


    public function checkCanSubmit(string $id, string $userId = null)
    {
        $assignment = $this->assignments->findOrThrow($id);
        $user = $this->getUserOrCurrent($userId);

        if (!$this->assignmentAcl->canSubmit($assignment, $user)) {
            throw new ForbiddenRequestException("You cannot access this assignment.");
        }
    }

    /**
     * Check if the given user can submit solutions to the assignment
     * @GET
     * @param string $id Identifier of the assignment
     * @param string|null $userId Identification of the user
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    public function actionCanSubmit(string $id, string $userId = null)
    {
        $assignment = $this->assignments->findOrThrow($id);
        $user = $this->getUserOrCurrent($userId);

        $this->sendSuccessResponse(
            [
                "canSubmit" => $this->canReceiveSubmissions($assignment, $user),
                "submittedCount" => count($this->assignmentSolutions->findValidSolutions($assignment, $user))
            ]
        );
    }

    /**
     * Submit a solution of an assignment
     * @POST
     * @Param(type="post", name="note", validation="string:0..1024",
     *        description="A note by the author of the solution")
     * @Param(type="post", name="userId", required=false, description="Author of the submission")
     * @Param(type="post", name="files", description="Submitted files")
     * @Param(type="post", name="runtimeEnvironmentId",
     *        description="Identifier of the runtime environment used for evaluation")
     * @Param(type="post", name="solutionParams", required=false, description="Solution parameters")
     * @param string $id Identifier of the assignment
     * @throws ForbiddenRequestException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws ParseException
     */
    public function actionSubmit(string $id)
    {
        $assignment = $this->assignments->findOrThrow($id);
        $req = $this->getRequest();
        $user = $this->getUserOrCurrent($req->getPost("userId"));

        if (!$this->assignmentAcl->canSubmit($assignment, $user)) {
            throw new ForbiddenRequestException();
        }

        if (!$this->canReceiveSubmissions($assignment, $user)) {
            throw new ForbiddenRequestException(
                "User '{$user->getId()}' cannot submit solutions for this assignment anymore."
            );
        }

        // retrieve and check uploaded files
        $uploadedFiles = $this->files->findAllById($req->getPost("files"));
        if (count($uploadedFiles) === 0) {
            throw new InvalidArgumentException("files", "No files were uploaded");
        }

        // preform basic checks on uploaded files
        foreach ($uploadedFiles as $file) {
            if ($file instanceof SolutionFile) {
                throw new ForbiddenRequestException(
                    "File {$file->getId()} was already used in a different submission."
                );
            }
        }

        // perform size/count limits checks on submitted files
        if (
            $assignment->getSolutionFilesLimit() !== null &&
            count($uploadedFiles) > $assignment->getSolutionFilesLimit()
        ) {
            throw new InvalidArgumentException("files", "Number of uploaded files exceeds assignment limits");
        }

        if (
            $assignment->getSolutionSizeLimit() !== null
            && $this->getFilesSize($uploadedFiles) > $assignment->getSolutionSizeLimit()
        ) {
            throw new InvalidArgumentException("files", "Total size of uploaded files exceeds assignment limits");
        }

        // create Solution object
        $runtimeEnvironment = $this->runtimeEnvironments->findOrThrow($req->getPost("runtimeEnvironmentId"));
        $solution = new Solution($user, $runtimeEnvironment);
        $solution->setSolutionParams(new SolutionParams($req->getPost("solutionParams")));

        // create and fill assignment solution
        $note = $req->getPost("note");
        $assignmentSolution = AssignmentSolution::createSolution($note, $assignment, $solution);
        $this->assignmentSolutions->persist($assignmentSolution);

        foreach ($uploadedFiles as $file) {
            $this->fileStorage->storeUploadedSolutionFile($assignmentSolution->getSolution(), $file);
            $solutionFile = SolutionFile::fromUploadedFile($file, $solution);
            $this->files->persist($solutionFile, false);
            $this->files->remove($file, false);
        }

        $this->sendSuccessResponse($this->finishSubmission($assignmentSolution));
    }

    /**
     * Take a complete submission entity and submit it to the backend
     * @param AssignmentSolution $solution that holds the files and everything
     * @param bool $isDebug
     * @return array The response that can be sent to the client
     * @throws ForbiddenRequestException
     * @throws InvalidArgumentException
     * @throws ParseException
     * @throws Exception
     */
    private function finishSubmission(AssignmentSolution $solution, bool $isDebug = false)
    {
        [ $submission, $jobConfig ] = $this->submissionHelper->submit($solution, $this->getCurrentUser(), $isDebug);

        // The solution needs to reload submissions (it is tedious and error prone to update them manually)
        $this->solutions->refresh($solution);

        return [
            "solution" => $this->assignmentSolutionViewFactory->getSolutionData($solution),
            "submission" => $this->assignmentSolutionSubmissionViewFactory->getSubmissionData($submission),
            "webSocketChannel" => [
                "id" => $jobConfig->getJobId(),
                "monitorUrl" => $this->monitorConfig->getAddress(),
                "expectedTasksCount" => $jobConfig->getTasksCount()
            ]
        ];
    }

    public function checkResubmit(string $id)
    {
        $solution = $this->assignmentSolutions->findOrThrow($id);
        if (!$this->assignmentAcl->canResubmitSubmissions($solution->getAssignment())) {
            throw new ForbiddenRequestException("You cannot resubmit this submission");
        }
    }

    /**
     * Resubmit a solution (i.e., create a new submission)
     * @POST
     * @param string $id Identifier of the solution
     * @Param(type="post", name="debug", validation="bool", required=false,
     *        "Debugging resubmit with all logs and outputs")
     * @throws ForbiddenRequestException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws ParseException
     */
    public function actionResubmit(string $id)
    {
        $req = $this->getRequest();
        $isDebug = filter_var($req->getPost("debug"), FILTER_VALIDATE_BOOLEAN);
        $solution = $this->assignmentSolutions->findOrThrow($id);

        $this->sendSuccessResponse($this->finishSubmission($solution, $isDebug));
    }

    public function checkResubmitAllAsyncJobStatus(string $id)
    {
        $assignment = $this->assignments->findOrThrow($id);
        if (!$this->assignmentAcl->canResubmitSubmissions($assignment)) {
            throw new ForbiddenRequestException("You cannot resubmit submissions to this assignment");
        }
    }

    /**
     * Return a list of all pending resubmit async jobs associated with given assignment.
     * Under normal circumstances, the list shoul be either empty, or contian only one job.
     * @GET
     * @param string $id Identifier of the assignment
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    public function actionResubmitAllAsyncJobStatus(string $id)
    {
        $assignment = $this->assignments->findOrThrow($id);
        $asyncJobs = $this->asyncJobs->findPendingJobs(ResubmitAllAsyncJobHandler::ID, false, null, $assignment);
        $failedJobs = $this->asyncJobs->findFailedJobs(ResubmitAllAsyncJobHandler::ID, null, $assignment);
        $this->sendSuccessResponse([ 'pending' => $asyncJobs, 'failed' => $failedJobs ]);
    }

    public function checkResubmitAll(string $id)
    {
        $assignment = $this->assignments->findOrThrow($id);
        if (!$this->assignmentAcl->canResubmitSubmissions($assignment)) {
            throw new ForbiddenRequestException("You cannot resubmit submissions to this assignment");
        }
    }

    /**
     * Start async job that resubmits all submissions of an assignment.
     * No job is started when there are pending resubmit jobs for the selected assignment.
     * Returns list of pending async jobs (same as GET call)
     * @POST
     * @param string $id Identifier of the assignment
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    public function actionResubmitAll(string $id)
    {
        $assignment = $this->assignments->findOrThrow($id);
        $asyncJobs = $this->asyncJobs->findPendingJobs(ResubmitAllAsyncJobHandler::ID, false, null, $assignment);
        $failedJobs = $this->asyncJobs->findFailedJobs(ResubmitAllAsyncJobHandler::ID, null, $assignment);
        if (!$asyncJobs) {
            // new job is started only if no async jobs are pending
            $asyncJob = ResubmitAllAsyncJobHandler::createAsyncJob($this->getCurrentUser(), $assignment);
            $this->asyncJobs->persist($asyncJob);
            $asyncJobs = [ $asyncJob ];
        }
        $this->sendSuccessResponse([ 'pending' => $asyncJobs, 'failed' => $failedJobs ]);
    }

    public function checkPreSubmit(string $id, string $userId = null)
    {
        $assignment = $this->assignments->findOrThrow($id);
        $user = $this->getUserOrCurrent($userId);

        if (!$this->assignmentAcl->canSubmit($assignment, $user)) {
            throw new ForbiddenRequestException("You cannot submit this assignment.");
        }
    }

    /**
     * Pre submit action which will, based on given files, detect possible runtime
     * environments for the assignment. Also it can be further used for entry
     * points and other important things that should be provided by user during
     * submit.
     * @POST
     * @param string $id identifier of assignment
     * @param string|null $userId Identifier of the submission author
     * @throws ExerciseConfigException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @Param(type="post", name="files", validation="array", "Array of identifications of submitted files")
     */
    public function actionPreSubmit(string $id, string $userId = null)
    {
        $assignment = $this->assignments->findOrThrow($id);

        // retrieve and check uploaded files
        $uploadedFiles = $this->files->findAllById($this->getRequest()->getPost("files"));
        if (count($uploadedFiles) === 0) {
            throw new InvalidArgumentException("files", "No files were uploaded");
        }

        // prepare file names into separate array
        $filenames = array_values(
            array_map(
                function (UploadedFile $uploadedFile) {
                    return $uploadedFile->getName();
                },
                $uploadedFiles
            )
        );

        $this->sendSuccessResponse(
            [
                "environments" => $this->exerciseConfigHelper->getEnvironmentsForFiles($assignment, $filenames),
                "submitVariables" => $this->exerciseConfigHelper->getSubmitVariablesForExercise($assignment),
                "countLimitOK" => $assignment->getSolutionFilesLimit() === null
                    || count($uploadedFiles) <= $assignment->getSolutionFilesLimit(),
                "sizeLimitOK" => $assignment->getSolutionSizeLimit() === null
                    || $this->getFilesSize($uploadedFiles) <= $assignment->getSolutionSizeLimit(),
            ]
        );
    }
}
