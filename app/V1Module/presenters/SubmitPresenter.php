<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\SubmissionFailedException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\NotFoundException;
use App\Exceptions\SubmissionEvaluationFailedException;

use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\MonitorConfig;
use App\Model\Entity\Solution;
use App\Model\Entity\SolutionFile;
use App\Model\Entity\Submission;
use App\Model\Entity\Assignment;
use App\Helpers\SubmissionHelper;
use App\Helpers\JobConfig;
use App\Helpers\JobConfig\Generator as JobConfigGenerator;
use App\Model\Entity\SubmissionFailure;
use App\Model\Repository\Assignments;
use App\Model\Repository\SubmissionFailures;
use App\Model\Repository\Submissions;
use App\Model\Repository\Solutions;
use App\Model\Repository\UploadedFiles;
use App\Model\Repository\RuntimeEnvironments;

use App\Security\ACL\IAssignmentPermissions;

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
   * @var Submissions
   * @inject
   */
  public $submissions;

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
   * @var MonitorConfig
   * @inject
   */
  public $monitorConfig;

  /**
   * @var JobConfig\Storage
   * @inject
   */
  public $jobConfigs;

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

    $this->sendSuccessResponse($assignment->canReceiveSubmissions($user));
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
   * @throws NotFoundException
   * @throws SubmissionEvaluationFailedException
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

    if (!$assignment->canReceiveSubmissions($loggedInUser)) {
      throw new ForbiddenRequestException("User '{$loggedInUser->getId()}' cannot submit solutions for this exercise any more.");
    }

    // retrieve and check uploaded files
    $uploadedFiles = $this->files->findAllById($req->getPost("files"));
    if (count($uploadedFiles) === 0) {
      throw new SubmissionEvaluationFailedException("No files were uploaded");
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

    // persist the new solution and flush all the changes to the files
    $this->solutions->persist($solution);

    // generate job configuration
    $compilationParams = CompilationParams::create($submittedFiles, false);
    list($jobConfigPath, $jobConfig) =
      $this->jobConfigGenerator->generateJobConfig($loggedInUser, $assignment,
        $runtimeEnvironment, $compilationParams);

    // create and persist submission in the database
    $note = $req->getPost("note");
    $submission = Submission::createSubmission($note, $assignment, $user, $loggedInUser, $solution, $jobConfigPath);
    $this->submissions->persist($submission);

    $this->sendSuccessResponse($this->finishSubmission($submission, $jobConfig));
  }

  private function submissionFailed(Submission $submission, string $message) {
    $failure = new SubmissionFailure(SubmissionFailure::TYPE_BROKER_REJECT, $message, $submission);
    $this->submissionFailures->persist($failure);
    throw new SubmissionFailedException($message);
  }

  /**
   * Take a complete submission entity and submit it to the backend
   * @param Submission $submission a persisted submission entity
   * @param JobConfig\JobConfig|null $jobConfig
   * @return array The response that can be sent to the client
   * @throws InvalidArgumentException
   */
  private function finishSubmission(Submission $submission, JobConfig\JobConfig $jobConfig = null) {
    if ($submission->getId() === NULL) {
      throw new InvalidArgumentException("The submission object is missing an id");
    }

    // load job configuration
    if (!$jobConfig) {
      $jobConfig = $this->jobConfigs->get($submission->getJobConfigPath());
    }

    // initiate submission
    $resultsUrl = null;
    try {
      $resultsUrl = $this->submissionHelper->submit(
        $submission->getId(),
        $submission->getSolution()->getRuntimeEnvironment()->getId(),
        $submission->getSolution()->getFiles()->getValues(),
        $jobConfig
      );
    } catch (\Exception $e) {
      $this->submissionFailed($submission, $e->getMessage());
    }

    // If the submission was accepted we now have the URL where to look for the results later -> persist it
    $submission->setResultsUrl($resultsUrl);
    $this->submissions->persist($submission);

    return [
      "submission" => $submission,
      "webSocketChannel" => [
        "id" => $jobConfig->getJobId(),
        "monitorUrl" => $this->monitorConfig->getAddress(),
        "expectedTasksCount" => $jobConfig->getTasksCount()
      ]
    ];
  }

  /**
   * Resubmit a submission (for example in case of broker failure)
   * @POST
   * @param string $id Identifier of the submission
   * @Param(type="post", name="private", validation="bool", "Flag the submission as private (not visible to students)")
   * @Param(type="post", name="debug", validation="bool", required=false, "Debugging resubmit with all logs and outputs")
   * @throws ForbiddenRequestException
   */
  public function actionResubmit(string $id) {
    $user = $this->getCurrentUser();
    $req = $this->getRequest();
    $isDebug = filter_var($req->getPost("debug"), FILTER_VALIDATE_BOOLEAN);
    $isPrivate = filter_var($req->getPost("private"), FILTER_VALIDATE_BOOLEAN);

    /** @var Submission $oldSubmission */
    $oldSubmission = $this->submissions->findOrThrow($id);
    if (!$this->assignmentAcl->canResubmitSubmissions($oldSubmission->getAssignment())) {
      throw new ForbiddenRequestException("You cannot resubmit this submission");
    }

    // generate job configuration
    $compilationParams = CompilationParams::create($oldSubmission->getSolution()->getFileNames(), $isDebug);
    list($jobConfigPath, $jobConfig) =
      $this->jobConfigGenerator->generateJobConfig($user,
        $oldSubmission->getAssignment(),
        $oldSubmission->getSolution()->getRuntimeEnvironment(),
        $compilationParams);

    $submission = Submission::createSubmission(
      $oldSubmission->getNote(), $oldSubmission->getAssignment(), $oldSubmission->getUser(), $user,
      $oldSubmission->getSolution(), $jobConfigPath, FALSE, $oldSubmission
    );

    $submission->setPrivate($isPrivate);

    // persist all the data in the database - this will also assign the UUID to the submission
    $this->submissions->persist($submission);

    $this->sendSuccessResponse($this->finishSubmission($submission, $jobConfig));
  }

  /**
   * Resubmit all submissions to an assignment
   * @POST
   * @param string $id Identifier of the assignment
   * @throws ForbiddenRequestException
   */
  public function actionResubmitAll(string $id) {
    $user = $this->getCurrentUser();

    /** @var Assignment $assignment */
    $assignment = $this->assignments->findOrThrow($id);

    if (!$this->assignmentAcl->canResubmitSubmissions($assignment)) {
      throw new ForbiddenRequestException("You cannot resubmit submissions to this assignment");
    }

    $result = [];

    /** @var Submission $oldSubmission */
    foreach ($assignment->getSubmissions() as $oldSubmission) {
      $submission = Submission::createSubmission(
        $oldSubmission->getNote(), $oldSubmission->getAssignment(), $oldSubmission->getUser(), $user,
        $oldSubmission->getSolution(), $oldSubmission->getJobConfigPath(), FALSE, $oldSubmission
      );

      // persist all the data in the database - this will also assign the UUID to the submission
      $this->submissions->persist($submission);

      $result[] = $this->finishSubmission($submission);
    }

    $this->sendSuccessResponse($result); // TODO better response format
  }
}
