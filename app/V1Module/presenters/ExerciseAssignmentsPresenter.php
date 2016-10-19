<?php

namespace App\V1Module\Presenters;

use App\Exceptions\NotFoundException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\BadRequestException;
use App\Exceptions\SubmissionFailedException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\JobConfigLoadingException;

use App\Model\Entity\Submission;
use App\Model\Entity\ExerciseAssignment;
use App\Helpers\SubmissionHelper;
use App\Helpers\JobConfig;
use App\Helpers\ScoreCalculatorFactory;
use App\Model\Repository\Exercises;
use App\Model\Repository\ExerciseAssignments;
use App\Model\Repository\Submissions;
use App\Model\Repository\UploadedFiles;
use App\Model\Repository\Groups;

/**
 * @LoggedIn
 */
class ExerciseAssignmentsPresenter extends BasePresenter {

  /**
   * @var Exercises
   * @inject
   */
  public $exercises;

  /**
   * @var ExerciseAssignments
   * @inject
   */
  public $assignments;

  /**
   * @var Submissions
   * @inject
   */
  public $submissions;

  /**
   * @var UploadedFiles
   * @inject
   */
  public $files;

  /**
   * @var Groups
   * @inject
   */
  public $groups; 

  /**
   * @var SubmissionHelper
   * @inject
   */
  public $submissionHelper;

  /**
   * @GET
   * @UserIsAllowed(assignments="view-all")
   */
  public function actionDefault() {
    $assignments = $this->assignments->findAll();
    $this->sendSuccessResponse($assignments);
  }

  /**
   * @GET
   * @UserIsAllowed(assignments="view-detail")
   */
  public function actionDetail(string $id) {
    $assignment = $this->assignments->findOrThrow($id);
    $user = $this->users->findCurrentUserOrThrow();

    if (!$assignment->canAccessAsStudent($user)
      && !$assignment->canAccessAsSupervisor($user)
      && $user->getRole()->hasLimitedRights()) {
        throw new ForbiddenRequestException("You cannot view this assignment.");
    }

    $this->sendSuccessResponse($assignment);
  }

  /**
   * @POST
   * @UserIsAllowed(assignments="update")
   * @Param(type="post", name="name", validation="string:2..")
   * @Param(type="post", name="isPublic", validation="bool")
   * @Param(type="post", name="description", validation="string")
   * @Param(type="post", name="firstDeadline", validation="numericint")
   * @Param(type="post", name="secondDeadline", validation="numericint")
   * @Param(type="post", name="firstMaxPoints", validation="numericint")
   * @Param(type="post", name="secondMaxPoints", validation="numericint")
   * @Param(type="post", name="submissionsLimit", validation="numericint")
   * @Param(type="post", name="scoreConfig", validation="string")
   */
  public function actionUpdateDetail(string $id) {
    $req = $this->getHttpRequest();
    $name = $req->getPost("name");
    $isPublic = filter_var($req->getPost("isPublic"), FILTER_VALIDATE_BOOLEAN);
    $description = $req->getPost("description");
    $firstDeadline = \DateTime::createFromFormat('U', $req->getPost("firstDeadline"));
    $secondDeadline = \DateTime::createFromFormat('U', $req->getPost("secondDeadline"));
    $firstMaxPoints = $req->getPost("firstMaxPoints");
    $secondMaxPoints = $req->getPost("secondMaxPoints");
    $submissionsLimit = $req->getPost("submissionsLimit");
    $scoreConfig = $req->getPost("scoreConfig");

    $assignment = $this->assignments->findOrThrow($id);
    $user = $this->users->findCurrentUserOrThrow();

    if (!$assignment->canAccessAsSupervisor($user)
      && $user->getRole()->hasLimitedRights()) {
        throw new ForbiddenRequestException("You cannot update this assignment.");
    }

    $assignment->setName($name);
    $assignment->setDescription($description);
    $assignment->setIsPublic($isPublic);
    $assignment->setFirstDeadline($firstDeadline);
    $assignment->setSecondDeadline($secondDeadline);
    $assignment->setMaxPointsBeforeFirstDeadline($firstMaxPoints);
    $assignment->setMaxPointsBeforeSecondDeadline($secondMaxPoints);
    $assignment->setSubmissionsCountLimit($submissionsLimit);
    $assignment->setScoreConfig($scoreConfig);

    $this->assignments->persist($assignment);
    $this->assignments->flush();

    $this->sendSuccessResponse($assignment);
  }

  /**
   * @POST
   * @UserIsAllowed(assignments="create")
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

    // test, if the user has privileges to the given group
    if ($group->isSupervisorOf($user) === FALSE && $user->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("Only supervisors of group '$groupId' can assign new exercises.");
    }

    // create an assignment for the group based on the given exercise but without any params
    // and make sure the assignment is not public yet - the supervisor must edit it first
    $assignment = ExerciseAssignment::assignToGroup($exercise, $group, FALSE);
    $assignment->setScoreConfig(self::getDefaultScoreConfig($assignment));

    $this->assignments->persist($assignment);
    $this->sendSuccessResponse($assignment);
  }

  private static function getDefaultScoreConfig(ExerciseAssignment $assignment): string {
    $jobConfigPath = $assignment->getJobConfigFilePath();
    try {
      $jobConfig = JobConfig\Storage::getJobConfig($jobConfigPath);
      $tests = array_map(
        function ($test) { return $test->getId(); },
        $jobConfig->getTests()
      );
      $defaultCalculatorClass = ScoreCalculatorFactory::getDefaultCalculatorClass();
      return $defaultCalculatorClass::getDefaultConfig($tests);
    } catch (JobConfigLoadingException $e) {
      return "";
    }
  }

  /**
   * @DELETE
   * @UserIsAllowed(assignments="remove")
   */
  public function actionRemove(string $id) {
    $assignment = $this->assignments->findOrThrow($id);
    $user = $this->users->findCurrentUserOrThrow();

    if (!$assignment->canAccessAsSupervisor($user)
      && $user->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("Only supervisors of the group can remove assigned exercises.");
    }

    $this->assignments->remove($assignment);
    $this->sendSuccessResponse("OK");
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
    $assignment = $this->assignments->findOrThrow($id);

    // get job config and its test cases
    $path = $assignment->getJobConfigFilePath();
    $jobConfig = JobConfig\Storage::getJobConfig($path);
    $tests = $jobConfig->getTests();

    // Array of test-id as a key and the value is another array of task-id and limits as Limits type 
    $listTestLimits = array_map(
      function ($test) use ($hardwareGroup) {
        return $test->getLimits($hardwareGroup);
      },
      $tests
    );

    // Convert the Limits type (as said above) to array representation
    $listTestArray = array_map(
      function ($limits) {
        $arrayLimits = [];
        foreach ($limits as $taskId => $limit) {
          $arrayLimits[$taskId] = $limit->toArray();
        }
        return $arrayLimits;
      },
      $listTestLimits
    );

    $this->sendSuccessResponse($listTestArray); 
      
  }

  /**
   * @POST
   * @UserIsAllowed(assignments="set-limits")
   * @Param(type="post", name="limits")
   */
  public function actionSetLimits(string $id, string $hardwareGroup) {
    $assignment = $this->assignments->findOrThrow($id);
    $limits = $this->getHttpRequest()->getPost("limits");

    if ($limits === NULL || !is_array($limits)) {
      throw new InvalidArgumentException("limits");
    }

    // get job config and its test cases
    $path = $assignment->getJobConfigFilePath();
    $jobConfig = JobConfig\Storage::getJobConfig($path);
    $jobConfig->setLimits($hardwareGroup, $limits);

    // save the new & archive the old config
    JobConfig\Storage::saveJobConfig($jobConfig, $path);

    $this->sendSuccessResponse($jobConfig->toArray());
  }
}
