<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Exceptions\ExerciseConfigException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\ParseException;
use App\Exceptions\InvalidApiArgumentException;
use App\Exceptions\NotFoundException;
use App\Helpers\FileStorageManager;
use App\Helpers\EntityMetadata\Solution\SolutionParams;
use App\Helpers\ExerciseConfig\Helper as ExerciseConfigHelper;
use App\Helpers\FailureHelper;
use App\Helpers\MonitorConfig;
use App\Helpers\SubmissionHelper;
use App\Helpers\JobConfig\Generator as JobConfigGenerator;
use App\Model\Entity\Solution;
use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\Assignment;
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
    private function canReceiveSubmissions(Assignment $assignment, ?User $user = null)
    {
        return $this->assignmentAcl->canSubmit($assignment, $user) &&
            $assignment->isVisibleToStudents() &&
            $assignment->getGroup() &&
            $assignment->getGroup()->hasValidLicense() &&
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
     * Check if the given user can submit solutions to the assignment
     * @GET
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "Identifier of the assignment", required: true)]
    #[Query("userId", new VString(), "Identification of the user", required: false, nullable: true)]
    public function actionCanSubmit(string $id, ?string $userId = null)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Submit a solution of an assignment
     * @POST
     * @throws ForbiddenRequestException
     * @throws InvalidApiArgumentException
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
    #[Path("id", new VUuid(), "Identifier of the assignment", required: true)]
    public function actionSubmit(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Take a complete submission entity and submit it to the backend
     * @param AssignmentSolution $solution that holds the files and everything
     * @param bool $isDebug
     * @return array The response that can be sent to the client
     * @throws ForbiddenRequestException
     * @throws InvalidApiArgumentException
     * @throws ParseException
     * @throws Exception
     */
    private function finishSubmission(AssignmentSolution $solution, bool $isDebug = false)
    {
        [$submission, $jobConfig] = $this->submissionHelper->submit($solution, $this->getCurrentUser(), $isDebug);

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


    /**
     * Resubmit a solution (i.e., create a new submission)
     * @POST
     * @throws ForbiddenRequestException
     * @throws InvalidApiArgumentException
     * @throws NotFoundException
     * @throws ParseException
     */
    #[Post("debug", new VBool(), "Debugging resubmit with all logs and outputs", required: false)]
    #[Path("id", new VUuid(), "Identifier of the solution", required: true)]
    public function actionResubmit(string $id)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Return a list of all pending resubmit async jobs associated with given assignment.
     * Under normal circumstances, the list should be either empty, or contain only one job.
     * @GET
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "Identifier of the assignment", required: true)]
    public function actionResubmitAllAsyncJobStatus(string $id)
    {
        $this->sendSuccessResponse("OK");
    }


    /**
     * Start async job that resubmits all submissions of an assignment.
     * No job is started when there are pending resubmit jobs for the selected assignment.
     * Returns list of pending async jobs (same as GET call)
     * @POST
     * @throws ForbiddenRequestException
     * @throws NotFoundException
     */
    #[Path("id", new VUuid(), "Identifier of the assignment", required: true)]
    public function actionResubmitAll(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Pre submit action which will, based on given files, detect possible runtime
     * environments for the assignment. Also it can be further used for entry
     * points and other important things that should be provided by user during
     * submit.
     * @POST
     * @throws ExerciseConfigException
     * @throws InvalidApiArgumentException
     * @throws NotFoundException
     */
    #[Post("files", new VArray())]
    #[Path("id", new VUuid(), "identifier of assignment", required: true)]
    #[Query("userId", new VString(), "Identifier of the submission author", required: false, nullable: true)]
    public function actionPreSubmit(string $id, string $userId = null)
    {
        $this->sendSuccessResponse("OK");
    }
}
