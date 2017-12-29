<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\SubmissionFailedException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\NotFoundException;

use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
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
use App\Model\Entity\User;
use App\Model\Repository\Assignments;
use App\Model\Repository\AssignmentSolutionSubmissions;
use App\Model\Repository\SubmissionFailures;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Repository\Solutions;
use App\Model\Repository\UploadedFiles;
use App\Model\Repository\RuntimeEnvironments;
use App\Model\View\AssignmentSolutionViewFactory;

use App\Security\ACL\IAssignmentPermissions;
use Exception;
use Nette\Http\IResponse;

/**
 * Endpoints for submitting an assignment
 * @LoggedIn
 */
class SubmitPresenter extends BasePresenter {

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
   * Determine if given user can submit solutions to assignment.
   * @param Assignment $assignment
   * @param User|NULL $user
   * @return bool
   */
  private function canReceiveSubmissions(Assignment $assignment, User $user = null) {
    return $assignment->isPublic() &&
      $assignment->getGroup()->hasValidLicence() &&
      ($user !== null &&
        count($this->assignmentSolutions->findValidSolutions($assignment, $user))
        <= $assignment->getSubmissionsCountLimit());
  }

  /**
   * Check if the current user can submit solutions to the assignment
   * @GET
   * @param string $id Identifier of the assignment
   * @throws ForbiddenRequestException
   */
  public function actionCanSubmit(string $id) {
    $assignment = $this->assignments->findOrThrow($id);
    $user = $this->getCurrentUser();

    if (!$this->assignmentAcl->canSubmit($assignment)) {
      throw new ForbiddenRequestException("You cannot access this assignment.");
    }

    $this->sendSuccessResponse($this->canReceiveSubmissions($assignment, $user));
  }

  /**
   * Submit a solution of an assignment
   * @POST
   * @Param(type="post", name="note", description="A private note by the author of the solution")
   * @Param(type="post", name="userId", required=false, description="Author of the submission")
   * @Param(type="post", name="files", description="Submitted files")
   * @Param(type="post", name="runtimeEnvironmentId", required=false, description="Identifier of the runtime environment used for evaluation")
   * @param string $id Identifier of the assignment
   * @throws ForbiddenRequestException
   * @throws InvalidArgumentException
   * @throws NotFoundException
   * @throws SubmissionFailedException
   */
  public function actionSubmit(string $id) {
    $assignment = $this->assignments->findOrThrow($id);
    $req = $this->getRequest();

    $loggedInUser = $this->getCurrentUser();
    $userId = $req->getPost("userId");
    $user = $userId !== NULL
      ? $this->users->findOrThrow($userId)
      : $loggedInUser;

    if (!$this->assignmentAcl->canSubmit($assignment)) {
      throw new ForbiddenRequestException();
    }

    if (!$this->canReceiveSubmissions($assignment, $loggedInUser)) {
      throw new ForbiddenRequestException("User '{$loggedInUser->getId()}' cannot submit solutions for this assignment anymore.");
    }

    // retrieve and check uploaded files
    $uploadedFiles = $this->files->findAllById($req->getPost("files"));
    if (count($uploadedFiles) === 0) {
      throw new InvalidArgumentException("files", "No files were uploaded");
    }

    // detect the runtime environment
    if ($req->getPost("runtimeEnvironmentId") === NULL) {
      $runtimeEnvironment = $this->runtimeEnvironments->detectOrThrow($assignment, $uploadedFiles);
    } else {
      $runtimeEnvironment = $this->runtimeEnvironments->findOrThrow($req->getPost("runtimeEnvironmentId"));
    }

    // create Solution object
    $solution = new Solution($user, $runtimeEnvironment);

    $submittedFiles = [];
    foreach ($uploadedFiles as $file) {
      if ($file instanceof SolutionFile) {
        throw new ForbiddenRequestException("File {$file->getId()} was already used in a different submission.");
      }

      $submittedFiles[] = $file->getName();
      $solutionFile = SolutionFile::fromUploadedFile($file, $solution);
      $this->files->persist($solutionFile, FALSE);
      $this->files->remove($file, FALSE);
    }

    // create and fill assignment solution
    $note = $req->getPost("note");
    $assignmentSolution = AssignmentSolution::createSolution($note, $assignment, $solution);

    // persist all changes and send response
    $this->assignmentSolutions->persist($assignmentSolution);
    $this->solutions->persist($solution);
    $this->sendSuccessResponse($this->finishSubmission($assignmentSolution));
  }

  /**
   * @throws SubmissionFailedException
   */
  private function submissionFailed(AssignmentSolutionSubmission $submission, string $message) {
    $failure = SubmissionFailure::forSubmission(SubmissionFailure::TYPE_BROKER_REJECT, $message, $submission);
    $this->submissionFailures->persist($failure);
    $this->failureHelper->report(FailureHelper::TYPE_BACKEND_ERROR, "Failed to send submission {$submission->getId()} to the broker");
    throw new SubmissionFailedException($message);
  }

  /**
   * Take a complete submission entity and submit it to the backend
   * @param AssignmentSolution $solution a persisted submission entity
   * @param bool $isDebug
   * @return array The response that can be sent to the client
   * @throws ForbiddenRequestException
   * @throws InvalidArgumentException
   */
  private function finishSubmission(AssignmentSolution $solution, bool $isDebug = false) {
    if ($solution->getId() === NULL) {
      throw new InvalidArgumentException("The submission object is missing an id");
    }

    // check for the license of instance of user
    $assignment = $solution->getAssignment();
    if ($assignment->getGroup()->hasValidLicence() === FALSE) {
      throw new ForbiddenRequestException("Your institution '{$assignment->getGroup()->getInstance()->getId()}' does not have a valid licence and you cannot submit solutions for any assignment in this group '{$assignment->getGroup()->getId()}'. Contact your supervisor for assistance.",
        IResponse::S402_PAYMENT_REQUIRED);
    }

    // generate job configuration
    $compilationParams = CompilationParams::create($solution->getSolution()->getFileNames(), $isDebug);
    $generatorResult =
      $this->jobConfigGenerator->generateJobConfig($this->getCurrentUser(),
        $solution->getAssignment(),
        $solution->getSolution()->getRuntimeEnvironment(),
        $compilationParams);

    // create submission entity
    $submission = new AssignmentSolutionSubmission($solution,
      $generatorResult->getJobConfigPath(), $this->getCurrentUser());
    $this->assignmentSubmissions->persist($submission);

    // initiate submission
    $resultsUrl = null;
    try {
      $resultsUrl = $this->submissionHelper->submit(
        $submission->getId(),
        $solution->getSolution()->getRuntimeEnvironment()->getId(),
        $solution->getSolution()->getFiles()->getValues(),
        $generatorResult->getJobConfig()
      );
    } catch (Exception $e) {
      $this->submissionFailed($submission, $e->getMessage());
    }

    // If the submission was accepted we now have the URL where to look for the results later -> persist it
    $submission->setResultsUrl($resultsUrl);
    $this->assignmentSubmissions->persist($submission);

    return [
      "submission" => $this->assignmentSolutionViewFactory->getSolutionData($solution),
      "webSocketChannel" => [
        "id" => $generatorResult->getJobConfig()->getJobId(),
        "monitorUrl" => $this->monitorConfig->getAddress(),
        "expectedTasksCount" => $generatorResult->getJobConfig()->getTasksCount()
      ]
    ];
  }

  /**
   * Resubmit a submission (for example in case of broker failure)
   * @POST
   * @param string $id Identifier of the submission
   * @Param(type="post", name="debug", validation="bool", required=false, "Debugging resubmit with all logs and outputs")
   * @throws ForbiddenRequestException
   */
  public function actionResubmit(string $id) {
    $req = $this->getRequest();
    $isDebug = filter_var($req->getPost("debug"), FILTER_VALIDATE_BOOLEAN);

    $solution = $this->assignmentSolutions->findOrThrow($id);
    if (!$this->assignmentAcl->canResubmitSubmissions($solution->getAssignment())) {
      throw new ForbiddenRequestException("You cannot resubmit this submission");
    }

    $this->sendSuccessResponse($this->finishSubmission($solution, $isDebug));
  }

  /**
   * Resubmit all submissions to an assignment
   * @POST
   * @param string $id Identifier of the assignment
   * @throws ForbiddenRequestException
   */
  public function actionResubmitAll(string $id) {
    $assignment = $this->assignments->findOrThrow($id);
    if (!$this->assignmentAcl->canResubmitSubmissions($assignment)) {
      throw new ForbiddenRequestException("You cannot resubmit submissions to this assignment");
    }

    /** @var AssignmentSolution $solution */
    $result = [];
    foreach ($assignment->getAssignmentSolutions() as $solution) {
      $result[] = $this->finishSubmission($solution, false);
    }

    $this->sendSuccessResponse($result);
  }
}
