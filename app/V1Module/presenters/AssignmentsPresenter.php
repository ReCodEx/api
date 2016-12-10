<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\SubmissionFailedException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\JobConfigLoadingException;
use App\Exceptions\NotFoundException;
use App\Exceptions\SubmissionEvaluationFailedException;

use App\Helpers\MonitorConfig;
use App\Helpers\ScoreCalculatorAccessor;
use App\Model\Entity\Solution;
use App\Model\Entity\SolutionFile;
use App\Model\Entity\Submission;
use App\Model\Entity\Assignment;
use App\Model\Entity\LocalizedAssignment;
use App\Helpers\SubmissionHelper;
use App\Helpers\JobConfig;
use App\Helpers\UploadedJobConfigStorage;
use App\Model\Entity\SubmissionFailure;
use App\Model\Repository\Assignments;
use App\Model\Repository\Exercises;
use App\Model\Repository\Groups;
use App\Model\Repository\ReferenceSolutionEvaluations;
use App\Model\Repository\SolutionRuntimeConfigs;
use App\Model\Repository\SubmissionFailures;
use App\Model\Repository\Submissions;
use App\Model\Repository\Solutions;
use App\Model\Repository\UploadedFiles;
use App\Model\Repository\RuntimeEnvironments;

use DateTime;
use Exception;
use Nette\InvalidStateException;
use Nette\Utils\Arrays;

/**
 * Endpoints for exercise assignment manipulation
 * @LoggedIn
 */
class AssignmentsPresenter extends BasePresenter {

  /**
   * @var Exercises
   * @inject
   */
  public $exercises;

  /**
   * @var Groups
   * @inject
   */
  public $groups;

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
   * @var SolutionRuntimeConfigs
   * @inject
   */
  public $runtimeConfigurations;

  /**
   * @var ReferenceSolutionEvaluations
   * @inject
   */
  public $referenceSolutionEvaluations;

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
   * @var ScoreCalculatorAccessor
   * @inject
   */
  public $calculators;

  /**
   * @var JobConfig\Storage
   * @inject
   */
  public $jobConfigs;

  /**
   * @var UploadedJobConfigStorage
   * @inject
   */
  public $uploadedJobConfigStorage;

  /**
   * @var RuntimeEnvironments
   * @inject
   */
  public $runtimeEnvironments;

  /**
   * Get a list of all assignments
   * @GET
   * @UserIsAllowed(assignments="view-all")
   */
  public function actionDefault() {
    $assignments = $this->assignments->findAll();
    $this->sendSuccessResponse($assignments);
  }

  /**
   * Get details of an assignment
   * @GET
   * @UserIsAllowed(assignments="view-detail")
   * @param string $id Identifier of the assignment
   * @throws ForbiddenRequestException
   */
  public function actionDetail(string $id) {
    $assignment = $this->assignments->findOrThrow($id);
    $user = $this->getCurrentUser();

    if (!$assignment->canAccessAsStudent($user)
        && !$assignment->canAccessAsSupervisor($user)
        && $user->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You cannot view this assignment.");
    }

    $this->sendSuccessResponse($assignment);
  }

  /**
   * Update details of an assignment
   * @POST
   * @UserIsAllowed(assignments="update")
   * @Param(type="post", name="name", validation="string:2..", description="Name of the assignment")
   * @Param(type="post", name="version", validation="numericint", description="Version of the edited exercise")
   * @Param(type="post", name="isPublic", validation="bool", description="Is the assignment ready to be displayed to students?")
   * @Param(type="post", name="localizedAssignments", description="A description of the assignment")
   * @Param(type="post", name="firstDeadline", validation="numericint", description="First deadline for submission of the assignment")
   * @Param(type="post", name="maxPointsBeforeFirstDeadline", validation="numericint", description="A maximum of points that can be awarded for a submission before first deadline")
   * @Param(type="post", name="submissionsCountLimit", validation="numericint", description="A maximum amount of submissions by a student for the assignment")
   * @Param(type="post", name="scoreConfig", validation="string", description="A configuration of the score calculator (the exact format depends on the calculator assigned to the exercise)")
   * @Param(type="post", name="allowSecondDeadline", validation="bool", description="Should there be a second deadline for students who didn't make the first one?")
   * @Param(type="post", name="canViewLimitRatios", validation="bool", description="Can user view ratio of his solution memory and time usages and assignment limits?")
   * @Param(type="post", name="secondDeadline", validation="numericint", required=false, description="A second deadline for submission of the assignment (with different point award)")
   * @Param(type="post", name="maxPointsBeforeSecondDeadline", validation="numericint", required=false, description="A maximum of points that can be awarded for a late submission")
   * @param string $id Identifier of the updated assignment
   * @throws ForbiddenRequestException
   */
  public function actionUpdateDetail(string $id) {
    $assignment = $this->assignments->findOrThrow($id);
    $user = $this->getCurrentUser();
    if (!$assignment->canAccessAsSupervisor($user)
        && $user->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You cannot update this assignment.");
    }

    $req = $this->getRequest();
    $version = intval($req->getPost("version"));
    if ($version !== $assignment->getVersion()) {
      throw new BadRequestException("The assignment was edited in the meantime and the version has changed. Current version is {$assignment->getVersion()}."); // @todo better exception
    }

    $assignment->setName($req->getPost("name"));
    $assignment->incrementVersion();
    $assignment->setIsPublic(filter_var($req->getPost("isPublic"), FILTER_VALIDATE_BOOLEAN));
    $assignment->setFirstDeadline(DateTime::createFromFormat('U', $req->getPost("firstDeadline")));
    $assignment->setSecondDeadline(DateTime::createFromFormat('U', $req->getPost("secondDeadline") ?: 0));
    $assignment->setMaxPointsBeforeFirstDeadline($req->getPost("maxPointsBeforeFirstDeadline"));
    $assignment->setMaxPointsBeforeSecondDeadline($req->getPost("maxPointsBeforeSecondDeadline") ?: 0);
    $assignment->setSubmissionsCountLimit($req->getPost("submissionsCountLimit"));
    $assignment->setScoreConfig($req->getPost("scoreConfig"));
    $assignment->setAllowSecondDeadline(filter_var($req->getPost("allowSecondDeadline"), FILTER_VALIDATE_BOOLEAN));
    $assignment->setCanViewLimitRatios(filter_var($req->getPost("canViewLimitRatios"), FILTER_VALIDATE_BOOLEAN));

    // add new and update old localizations
    $postLocalized = $req->getPost("localizedAssignments");
    $localizedAssignments = $postLocalized && is_array($postLocalized)? $postLocalized : array();
    $usedLocale = [];
    foreach ($localizedAssignments as $localization) {
      $lang = $localization["locale"];
      $description = $localization["description"];
      $localizationName = $localization["name"];

      // create all new localized assignments
      $originalLocalized = $assignment->getLocalizedAssignmentByLocale($lang);
      $localized = new LocalizedAssignment($localizationName, $description, $lang);
      if ($originalLocalized) {
        $localized->setLocalizedAssignment($originalLocalized);
        $assignment->removeLocalizedAssignment($originalLocalized);
      }
      $assignment->addLocalizedAssignment($localized);
      $usedLocale[] = $lang;
    }

    // remove unused languages
    foreach ($assignment->getLocalizedAssignments() as $localization) {
      if (!in_array($localization->getLocale(), $usedLocale)) {
        $assignment->removeLocalizedAssignment($localization);
      }
    }

    $this->assignments->persist($assignment);
    $this->assignments->flush();

    $this->sendSuccessResponse($assignment);
  }

  /**
   * Check if the version of the assignment is up-to-date.
   * @POST
   * @UserIsAllowed(assignments="update")
   * @Param(type="post", name="version", validation="", description="Version of the assignment.")
   * @param string $id Identifier of the assignment
   */
  public function actionValidate($id) {
    $assignment = $this->assignments->findOrThrow($id);
    $user = $this->getCurrentUser();

    if (!$assignment->canAccessAsSupervisor($user)
        && $user->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("You cannot access this assignment.");
    }

    $req = $this->getHttpRequest();
    $version = intval($req->getPost("version"));

    $this->sendSuccessResponse([
      "versionIsUpToDate" => $assignment->getVersion() === $version
    ]);
  }

  /**
   * Assign an exercise to a group
   * @POST
   * @UserIsAllowed(assignments="create")
   * @Param(type="post", name="exerciseId", description="Identifier of the exercise")
   * @Param(type="post", name="groupId", description="Identifier of the group")
   */
  public function actionCreate() {
    $req = $this->getRequest();
    $exerciseId = $req->getPost("exerciseId");
    $groupId = $req->getPost("groupId");

    $group = $this->groups->findOrThrow($groupId);
    $user = $this->getCurrentUser();

    $exercise = $this->exercises->findOrThrow($exerciseId);
    if (!$exercise->canAccessDetail($user)) {
      throw new NotFoundException("Exercise was not found");
    }

    // test, if the user has privileges to the given group
    if ($group->isSupervisorOf($user) === FALSE && $user->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("Only supervisors of group '$groupId' can assign new exercises.");
    }

    // create an assignment for the group based on the given exercise but without any params
    // and make sure the assignment is not public yet - the supervisor must edit it first
    $assignment = Assignment::assignToGroup($exercise, $group, FALSE);
    $this->uploadedJobConfigStorage->copyToUserAndUpdateRuntimeConfigs($assignment, $user);
    $assignment->setScoreConfig($this->getDefaultScoreConfig($assignment));
    $this->assignments->persist($assignment);
    $this->sendSuccessResponse($assignment);
  }

  private function getDefaultScoreConfig(Assignment $assignment): string {
    if (count($assignment->getSolutionRuntimeConfigs()) === 0) {
      throw new InvalidStateException("Assignment has no runtime configurations");
    }

    $runtimeConfig = $assignment->getSolutionRuntimeConfigs()->first();
    $jobConfigPath = $runtimeConfig->getJobConfigFilePath();
    try {
      $jobConfig = $this->jobConfigs->getJobConfig($jobConfigPath);
      $tests = array_map(
        function ($test) { return $test->getId(); },
        $jobConfig->getTests()
      );
      return $this->calculators->getDefaultCalculator()->getDefaultConfig($tests);
    } catch (JobConfigLoadingException $e) {
      return "";
    }
  }

  /**
   * Delete an assignment
   * @DELETE
   * @UserIsAllowed(assignments="remove")
   * @param string $id Identifier of the assignment to be removed
   * @throws ForbiddenRequestException
   */
  public function actionRemove(string $id) {
    $assignment = $this->assignments->findOrThrow($id);
    $user = $this->getCurrentUser();

    if (!$assignment->canAccessAsSupervisor($user)
      && $user->getRole()->hasLimitedRights()) {
      throw new ForbiddenRequestException("Only supervisors of the group can remove assigned exercises.");
    }

    $this->assignments->remove($assignment);
    $this->sendSuccessResponse("OK");
  }

  /**
   * Check if the current user can submit solutions to the assignment
   * @GET
   * @UserIsAllowed(assignments="submit")
   * @param string $id Identifier of the assignment
   * @throws ForbiddenRequestException
   */
  public function actionCanSubmit(string $id) {
    $assignment = $this->assignments->findOrThrow($id);
    $user = $this->getCurrentUser();

    if (!$assignment->canAccessAsStudent($user)
        && !$assignment->canAccessAsSupervisor($user)) {
      throw new ForbiddenRequestException("You cannot access this assignment.");
    }

    $this->sendSuccessResponse($assignment->canReceiveSubmissions($user));
  }

  /**
   * Get a list of solutions submitted by a user of an assignment
   * @GET
   * @UserIsAllowed(assignments="view-submissions")
   * @param string $id Identifier of the assignment
   * @param string $userId Identifier of the user
   * @throws ForbiddenRequestException
   */
  public function actionSubmissions(string $id, string $userId) {
    $assignment = $this->assignments->findOrThrow($id);
    $submissions = $this->submissions->findSubmissions($assignment, $userId);
    $currentUser = $this->getCurrentUser();

    $isFileOwner = $userId === $currentUser->getId();
    $isSupervisor = $assignment->getGroup()->isSupervisorOf($currentUser);
    $isAdmin = $assignment->getGroup()->isAdminOf($currentUser) || !$currentUser->getRole()->hasLimitedRights();

    if (!$isFileOwner && !$isSupervisor && !$isAdmin) {
      throw new ForbiddenRequestException("You cannot access these submissions");
    }

    $this->sendSuccessResponse($submissions);
  }

  /**
   * Submit a solution of an assignment
   * @POST
   * @UserIsAllowed(assignments="submit")
   * @Param(type="post", name="note", description="A private note by the author of the solution")
   * @Param(type="post", name="userId", required=false, description="Author of the submission")
   * @Param(type="post", name="files", description="Submitted files")
   * @Param(type="post", name="runtimeEnvironmentId", required=false, description="Identifier of the runtime environment used for evaluation")
   * @param string $id Identifier of the assignment
   * @throws ForbiddenRequestException
   */
  public function actionSubmit(string $id) {
    $assignment = $this->assignments->findOrThrow($id);
    $req = $this->getRequest();

    $loggedInUser = $this->getCurrentUser();
    $userId = $req->getPost("userId");
    $user = $userId !== NULL
      ? $this->users->findOrThrow($userId)
      : $loggedInUser;

    if (!$assignment->canReceiveSubmissions($loggedInUser)) {
      throw new ForbiddenRequestException("User '{$loggedInUser->getId()}' cannot submit solutions for this exercise any more.");
    }

    // retrieve and check uploaded files
    $uploadedFiles = $this->files->findAllById($req->getPost("files"));
    if (count($uploadedFiles) === 0) {
      throw new SubmissionEvaluationFailedException("No files were uploaded");
    }

    // detect the runtime configuration
    if ($req->getPost("runtimeEnvironmentId") === NULL) {
      $runtimeConfiguration = $this->runtimeConfigurations->detectOrThrow($assignment, $uploadedFiles);
    } else {
      $runtimeEnvironment = $this->runtimeEnvironments->findOrThrow($req->getPost("runtimeEnvironmentId"));
      $runtimeConfiguration = $assignment->getRuntimeConfigByEnvironment($runtimeEnvironment);
      if ($runtimeConfiguration === NULL) {
        throw new NotFoundException("RuntimeConfiguration was not found");
      }
    }

    // create Solution object
    $solution = new Solution($user, $runtimeConfiguration);

    foreach ($uploadedFiles as $file) {
      if ($file instanceof SolutionFile) {
        throw new ForbiddenRequestException("File {$file->getId()} was already used in a different submission.");
      }

      $solutionFile = SolutionFile::fromUploadedFile($file, $solution);
      $this->files->persist($solutionFile, FALSE);
      $this->files->remove($file, FALSE);
    }

    // persist the new solution and flush all the changes to the files
    $this->solutions->persist($solution);

    // submit the solution
    $note = $req->getPost("note");
    $submission = Submission::createSubmission($note, $assignment, $user, $loggedInUser, $solution);

    // persist all the data in the database - this will also assign the UUID to the submission
    $this->submissions->persist($submission);

    // get the job config with correct job id
    $path = $runtimeConfiguration->getJobConfigFilePath();
    $jobConfig = $this->jobConfigs->getJobConfig($path);
    $jobConfig->getSubmissionHeader()->setId($submission->getId())->setType(Submission::JOB_TYPE);
    $resultsUrl = NULL;

    try {
      $resultsUrl = $this->submissionHelper->initiateEvaluation(
        $jobConfig,
        $submission->getSolution()->getFiles()->getValues(),
        ['env' => $runtimeConfiguration->runtimeEnvironment->id]
      );
    } catch (Exception $e) {
      $this->submissionFailed($submission, $e->getMessage());
    }

    // if the submission was accepted we now have the URL where to look for the results later
    if($resultsUrl === NULL) {
      $this->submissionFailed($submission, "The broker rejected our request");
      return;
    }

    $submission->setResultsUrl($resultsUrl);
    $this->submissions->persist($submission);
    $this->sendSuccessResponse([
      "submission" => $submission,
      "webSocketChannel" => [
        "id" => $jobConfig->getJobId(),
        "monitorUrl" => $this->monitorConfig->getAddress(),
        "expectedTasksCount" => $jobConfig->getTasksCount()
      ]
    ]);
  }

  private function submissionFailed(Submission $submission, string $message) {
    $failure = new SubmissionFailure(SubmissionFailure::TYPE_BROKER_REJECT, $message, $submission);
    $this->submissionFailures->persist($failure);
    throw new SubmissionFailedException($message);
  }

  /**
   * Get a description of resource limits for an assignment
   * @GET
   * @UserIsAllowed(assignments="view-limits")
   * @param string $id Identifier of the exercise
   */
  public function actionGetLimits(string $id) {
    $assignment = $this->assignments->findOrThrow($id);

    // get job config and its test cases
    $environments = $assignment->getSolutionRuntimeConfigs()->map(
      function ($environment) use ($assignment) {
        $jobConfig = $this->jobConfigs->getJobConfig($environment->getJobConfigFilePath());
        $referenceEvaluations = [];
        foreach ($jobConfig->getHardwareGroups() as $hwGroup) {
          $referenceEvaluations[$hwGroup] = $this->referenceSolutionEvaluations->find(
            $assignment->getExercise(),
            $environment->getRuntimeEnvironment(),
            $hwGroup
          );
        }

        return [
          "environment" => $environment,
          "hardwareGroups" => $jobConfig->getHardwareGroups(),
          "limits" => $jobConfig->getLimits(),
          "referenceSolutionsEvaluations" => $referenceEvaluations
        ];
      }
    );

    $this->sendSuccessResponse([ "environments" => $environments->getValues() ]);
  }

  /**
   * Set resource limits for an assignment
   * @POST
   * @UserIsAllowed(assignments="set-limits")
   * @Param(type="post", name="environments", description="A list of resource limits for the environments and hardware groups", validation="array")
   * @param string $id Identifier of the assignment
   * @throws ForbiddenRequestException
   * @throws InvalidArgumentException
   * @throws NotFoundException
   */
  public function actionSetLimits(string $id) {
    $assignment = $this->assignments->findOrThrow($id);
    $assignmentRuntimeConfigsIds = $assignment->getSolutionRuntimeConfigsIds();

    $req = $this->getRequest();
    $environments = $req->getPost("environments");

    if (count($environments) === 0) {
      throw new NotFoundException("No environment specified");
    }

    foreach ($environments as $environment) {
      $runtimeId = Arrays::get($environment, ["environment", "id"], NULL);
      $runtimeConfig = $this->runtimeConfigurations->findOrThrow($runtimeId);
      if (!in_array($runtimeId, $assignmentRuntimeConfigsIds)) {
        throw new ForbiddenRequestException("Cannot configure solution runtime configuration $runtimeId for assignment $id");
      }

      // open the job config and update the limits for all hardware groups
      $path = $runtimeConfig->getJobConfigFilePath();
      $jobConfig = $this->jobConfigs->getJobConfig($path);

      // get through all defined limits indexed by hwgroup
      $limits = Arrays::get($environment, ["limits"], []);
      foreach ($limits as $hwGroupLimits) {
        if (!isset($hwGroupLimits["hardwareGroup"])) {
          throw new InvalidArgumentException("environments[][limits][][hardwareGroup]");
        }

        $hardwareGroup = $hwGroupLimits["hardwareGroup"];
        $tests = Arrays::get($hwGroupLimits, ["tests"], []);
        $limits = array_reduce(array_values($tests), "array_merge", []);
        $jobConfig->setLimits($hardwareGroup, $limits);
      }

      // save the new & archive the old config
      $this->jobConfigs->saveJobConfig($jobConfig, $path);
    }

    // the same output as get limits
    $this->forward("getLimits", $id);
  }
}
