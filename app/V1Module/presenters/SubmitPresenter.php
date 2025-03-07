<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Type;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VDouble;
use App\Helpers\MetaFormats\Validators\VEmail;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Helpers\MetaFormats\Validators\VUuid;
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
use App\Helpers\SubmissionHelper;
use App\Helpers\JobConfig\Generator as JobConfigGenerator;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\Solution;
use App\Model\Entity\SolutionFile;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\Assignment;
use App\Model\Entity\AssignmentSolver;
use App\Model\Entity\SubmissionFailure;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\User;
use App\Model\Repository\Assignments;
use App\Model\Repository\AssignmentSolutionSubmissions;
use App\Model\Repository\AsyncJobs;
use App\Model\Repository\SubmissionFailures;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Repository\AssignmentSolvers;
use App\Model\Repository\Solutions;
use App\Model\Repository\UploadedFiles;
use App\Model\Repository\RuntimeEnvironments;
use App\Model\View\AssignmentSolutionViewFactory;
use App\Model\View\AssignmentSolutionSubmissionViewFactory;
use App\Security\ACL\IAssignmentPermissions;
use App\Async\Dispatcher;
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
     * @var Dispatcher
     * @inject
     */
    public $dispatcher;

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
     * @var AssignmentSolvers
     * @inject
     */
    public $assignmentSolvers;

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
        return $this->assignmentAcl->canSubmit($assignment, $user) &&
            $assignment->isVisibleToStudents() &&
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

    public function checkCanSubmit(string $id, string $userId = null)
    {
        $assignment = $this->assignments->findOrThrow($id);

        if (!$this->assignmentAcl->canViewDetail($assignment)) {
            throw new ForbiddenRequestException("You cannot access this assignment.");
        }
    }

    /**
     * Check if the given user can submit solutions to the assignment
     * @GET
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    #[Path("id", new VString(), "Identifier of the assignment", required: true)]
    #[Query("userId", new VString(), "Identification of the user", required: false, nullable: true)]
    public function actionCanSubmit(string $id, string $userId = null)
    {
        $assignment = $this->assignments->findOrThrow($id);
        $user = $this->getUserOrCurrent($userId);

        $response = $this->assignmentSolutions->getSolutionStats($assignment, $user);
        $response['canSubmit'] = $this->canReceiveSubmissions($assignment, $user);
        $response['submittedCount'] = $response['evaluated']; // BC, DEPRECATED, will be removed in future
        if ($this->submissionHelper->isLocked()) {
            $response['canSubmit'] = false;  // override
            $response['lockedReason'] = $this->submissionHelper->getLockedReason();
        }

        $this->sendSuccessResponse($response);
    }

    /**
     * Submit a solution of an assignment
     * @POST
     * @throws ForbiddenRequestException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws ParseException
     */
    #[Post("note", new VString(0, 1024), "A note by the author of the solution")]
    #[Post("userId", new VMixed(), "Author of the submission", required: false, nullable: true)]
    #[Post("files", new VMixed(), "Submitted files", nullable: true)]
    #[Post(
        "runtimeEnvironmentId",
        new VMixed(),
        "Identifier of the runtime environment used for evaluation",
        nullable: true,
    )]
    #[Post("solutionParams", new VMixed(), "Solution parameters", required: false, nullable: true)]
    #[Path("id", new VString(), "Identifier of the assignment", required: true)]
    public function actionSubmit(string $id)
    {
        $this->assignments->beginTransaction();
        try {
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

            // get all uploaded files based on given ID list and verify them
            $uploadedFiles = $this->submissionHelper->getUploadedFiles(
                $req->getPost("files"),
                $assignment->getSolutionFilesLimit(),
                $assignment->getSolutionSizeLimit()
            );

            // create Solution object
            $runtimeEnvironment = $this->runtimeEnvironments->findOrThrow($req->getPost("runtimeEnvironmentId"));
            $solution = new Solution($user, $runtimeEnvironment);
            $solution->setSolutionParams(new SolutionParams($req->getPost("solutionParams")));

            // this may not be entirely atomic (depending on the isolation level), but hey, the worst thing
            // that might happen is that the attempt counting will be a little off ... no big deal
            $attemptIndex = $this->assignmentSolvers->getNextAttemptIndex($assignment, $user);

            // create and fill assignment solution
            $note = $req->getPost("note");
            $assignmentSolution = AssignmentSolution::createSolution($note, $assignment, $solution, $attemptIndex);
            $this->assignmentSolutions->persist($assignmentSolution);

            // convert uploaded files into solutions files and manage them in the storage correctly
            $this->submissionHelper->prepareUploadedFilesForSubmit($uploadedFiles, $assignmentSolution->getSolution());

            $this->assignments->commit();
        } catch (Exception $e) {
            $this->assignments->rollback();
            throw $e;
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

        $assignment = $solution->getAssignment();
        $user = $solution->getSolution()->getAuthor();
        if ($assignment && $user) {
            $this->assignmentSolvers->incrementEvaluationsCount($assignment, $user);
        }

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
     * @throws ForbiddenRequestException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws ParseException
     */
    #[Post("debug", new VBool(), "Debugging resubmit with all logs and outputs", required: false)]
    #[Path("id", new VString(), "Identifier of the solution", required: true)]
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
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    #[Path("id", new VString(), "Identifier of the assignment", required: true)]
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
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    #[Path("id", new VString(), "Identifier of the assignment", required: true)]
    public function actionResubmitAll(string $id)
    {
        $assignment = $this->assignments->findOrThrow($id);
        $asyncJobs = $this->asyncJobs->findPendingJobs(ResubmitAllAsyncJobHandler::ID, false, null, $assignment);
        $failedJobs = $this->asyncJobs->findFailedJobs(ResubmitAllAsyncJobHandler::ID, null, $assignment);
        if (!$asyncJobs) {
            // new job is started only if no async jobs are pending
            $asyncJob = ResubmitAllAsyncJobHandler::dispatchAsyncJob(
                $this->dispatcher,
                $this->getCurrentUser(),
                $assignment
            );
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
     * @throws ExerciseConfigException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    #[Post("files", new VArray())]
    #[Path("id", new VString(), "identifier of assignment", required: true)]
    #[Query("userId", new VString(), "Identifier of the submission author", required: false, nullable: true)]
    public function actionPreSubmit(string $id, string $userId = null)
    {
        $assignment = $this->assignments->findOrThrow($id);

        // retrieve and check uploaded files
        $uploadedFiles = $this->submissionHelper->getUploadedFiles($this->getRequest()->getPost("files"));

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
                    || $this->submissionHelper->getFilesSize($uploadedFiles) <= $assignment->getSolutionSizeLimit(),
            ]
        );
    }
}
