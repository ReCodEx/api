<?php

namespace App\V1Module\Presenters;

use App\Exceptions\NotFoundException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\BadRequestException;
use App\Exceptions\SubmissionFailedException;
use App\Exceptions\InvalidArgumentException;

use App\Model\Entity\Submission;
use App\Model\Entity\ExerciseAssignment;
use App\Helpers\SubmissionHelper;
use App\Helpers\JobConfig;
use App\Model\Repository\Exercises;
use App\Model\Repository\ExerciseAssignments;
use App\Model\Repository\Submissions;
use App\Model\Repository\UploadedFiles;

/**
 * @LoggedIn
 */
class ExerciseAssignmentsPresenter extends BasePresenter {

  /** @inject @var Exercises */
  public $exercises;

  /** @inject @var ExerciseAssignments */
  public $assignments;

  /** @inject @var Submissions */
  public $submissions;

  /** @inject @var UploadedFiles */
  public $files;

  /** @inject @var SubmissionHelper */
  public $submissionHelper;

  /**
   * @GET
   * @UserIsAllowed(assignments="view-all")
   */
  public function actionDefault() {
    $assignments = $this->assignments->findAll();
    $user = $this->users->findCurrentUserOrThrow();
    $personalizedData = $assignments->map(
      function ($assignment) {
        return $assignment->getJsonData($user);
      }
    );
    $this->sendSuccessResponse($personalizedData);
  }

  /**
   * @GET
   * @UserIsAllowed(assignments="view-detail")
   */
  public function actionDetail(string $id) {
    $assignment = $this->assignments->findOrThrow($id);
    $user = $this->users->findCurrentUserOrThrow();

    if (!$assignment->canAccessAsStudent($user)
      && !$assignment->canAccessAsSupervisor($user)) {
        throw new ForbiddenRequestException("You cannot view this assignment.");
    }

    $this->sendSuccessResponse($assignment);
  }

  /**
   * @GET
   * @Param(type="post", name="exerciseId")
   * @Param(type="post", name="groupId")
   */
  public function actionCreate() {
    $req = $this->getHttpRequest();
    $exerciseId = $req->getPost("exerciseId");
    $groupId = $req->getPost("groupId");

    $exercise = $this->exercises->findOrThrow($exerciseId);
    $group = $this->groups->findOrThrow($groupId);
    $user = $this->users->findCurrentUserOrThrow();

    // test, if the user has privilidges to the given group
    if ($group->isSupervisorOf($user) === FALSE) {
      throw new ForbiddenRequestException("Only supervisors of group '$groupId' can assign new exercises.");
    }

    // create an assignment for the group based on the given exercise but without any params
    // and make sure the assignment is not public yet - the supervisor must edit it first
    $assignment = ExerciseAssignment::assignExerciseToGroup($exercise, $group, FALSE);
    $this->assignments->persist($assignment);
    $this->sendSuccessResponse($assignment);
  }

  /**
   * @GET
   * @UserIsAllowed(assignments="submit")
   */
  public function actionCanSubmit(string $id) {
    $assignment = $this->assignments->findOrThrow($id);
    $user = $this->users->findCurrentUserOrThrow();

    if (!$assignment->canAccessAsStudent($user)
      && !$assignment->canAccessAsSupervisor($user)) {
        throw new ForbiddenRequestException("You cannot access this assignment.");
    }

    $this->sendSuccessResponse($assignment->canReceiveSubmissions($user));
  }

  /**
   * @GET
   * @UserIsAllowed(assignments="view-submissions")
   */
  public function actionSubmissions(string $id, string $userId) {
    $assignment = $this->assignments->findOrThrow($id);
    $submissions = $this->submissions->findSubmissions($assignment, $userId);
    $this->sendSuccessResponse($submissions);
  }

  /**
   * @POST
   * @UserIsAllowed(assignments="submit")
   * @Param(type="post", name="note")
   * @Param(type="post", name="files")
   */
  public function actionSubmit(string $id) {
    $assignment = $this->assignments->findOrThrow($id);
    $req = $this->getHttpRequest();

    $loggedInUser = $this->users->findCurrentUserOrThrow();
    $userId = $req->getPost("userId");
    if ($userId !== NULL) {
      $user = $this->users->findOrThrow($userId);
    } else {
      $user = $loggedInUser;
    }

    if (!$assignment->canReceiveSubmissions($loggedInUser)) {
      throw new ForbiddenRequestException("User '{$loggedInUser->getId()}' cannot submit solutions for this exercise any more.");
    }

    // create the submission record
    $hwGroup = "group1";
    $files = $this->files->findAllById($req->getPost("files"));
    $note = $req->getPost("note");
    $submission = Submission::createSubmission($note, $assignment, $user, $loggedInUser, $hwGroup, $files);

    // persist all the data in the database - this will also assign the UUID to the submission
    $this->submissions->persist($submission);

    // get the job config with correct job id
    $path = $submission->getExerciseAssignment()->getJobConfigFilePath();
    $jobConfig = JobConfig\Storage::getJobConfig($path);
    $jobConfig->setJobId(Submission::JOB_TYPE, $submission->getId());

    $resultsUrl = $this->submissionHelper->initiateEvaluation(
      $jobConfig,
      $submission->getSolution()->getFiles()->getValues(),
      $hwGroup
    );

    if($resultsUrl !== NULL) {
      $submission->setResultsUrl($resultsUrl);
      $this->submissions->persist($submission);
      $this->sendSuccessResponse([
        "submission" => $submission,
        "webSocketChannel" => [
          "id" => $jobConfig->getJobId(),
          "monitorUrl" => $this->getContext()->parameters['monitor']['address'],
          "expectedTasksCount" => $jobConfig->getTasksCount()
        ],
      ]);
    } else {
      throw new SubmissionFailedException;
    }
  }

  /**
   * @GET
   * @UserIsAllowed(assignments="view-limits")
   */
  public function actionGetLimits(string $id, string $hardwareGroup) {
    $assignment = $this->findAssignmentOrThrow($id);

    // get job config and its test cases
    $path = $assignment->getJobConfigFilePath();
    $jobConfig = JobConfig\Storage::getJobConfig($path);
    $tests = $jobConfig->getTests();

    $this->sendSuccessResponse(
      array_map(
        function ($test) use ($hardwareGroup) {
          return $test->getLimits($hardwareGroup)->getValues();
        },
        $tests
      )
    );
  }

  /**
   * @POST
   * @UserIsAllowed(assignments="set-limits")
   * @Param(type="post", name="limits")
   */
  public function actionSetLimits(string $id, string $hardwareGroup) {
    $assignment = $this->findAssignmentOrThrow($id);
    $limits = $this->getHttpRequest()->getPost("limits");

    if ($limits === NULL || !is_array($limits)) {
      throw new InvalidArgumentException("limits");
    }

    // get job config and its test cases
    $path = $assignment->getJobConfigFilePath();
    $jobConfig = JobConfig\Storage::getJobConfig($path);
    $newJobConfig = $jobConfig->cloneWithNewLimits($hardwareGroup, $limits);

    // save the new & archive the old config
    $archivedFilePath = JobConfig\Storage::saveJobConfig($newJobConfig, $path, TRUE); // @todo: remove the 'TRUE' so the configs are archived as soon as the next todo in this method is implemented
    if ($archivedFilePath !== NULL) {
      // @todo: where to store the old job config file names?
    }

    $this->sendSuccessResponse($newJobConfig->getValues());
  }
}
