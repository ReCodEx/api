<?php

namespace App\V1Module\Presenters;

use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\InvalidStateException;

use App\Helpers\Notifications\AssignmentEmailsSender;
use App\Model\Entity\Exercise;
use App\Model\Entity\Group;
use App\Model\Entity\Submission;
use App\Model\Entity\Assignment;
use App\Model\Entity\LocalizedExercise;

use App\Helpers\ExerciseConfig\Loader as ExerciseConfigLoader;
use App\Helpers\ScoreCalculatorAccessor;

use App\Model\Repository\Assignments;
use App\Model\Repository\Exercises;
use App\Model\Repository\Groups;
use App\Model\Repository\Submissions;

use App\Security\ACL\IAssignmentPermissions;
use App\Security\ACL\IGroupPermissions;
use App\Security\ACL\ISubmissionPermissions;
use DateTime;

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
   * @var ScoreCalculatorAccessor
   * @inject
   */
  public $calculators;

  /**
   * @var IAssignmentPermissions
   * @inject
   */
  public $assignmentAcl;

  /**
   * @var IGroupPermissions
   * @inject
   */
  public $groupAcl;

  /**
   * @var ISubmissionPermissions
   * @inject
   */
  public $submissionAcl;

  /**
   * @var ExerciseConfigLoader
   * @inject
   */
  public $exerciseConfigLoader;

  /**
   * @var AssignmentEmailsSender
   * @inject
   */
  public $assignmentEmailsSender;


  /**
   * Get a list of all assignments
   * @GET
   */
  public function actionDefault() {
    if (!$this->assignmentAcl->canViewAll()) {
      throw new ForbiddenRequestException();
    }

    $assignments = $this->assignments->findAll();
    $this->sendSuccessResponse($assignments);
  }

  /**
   * Get details of an assignment
   * @GET
   * @param string $id Identifier of the assignment
   * @throws ForbiddenRequestException
   */
  public function actionDetail(string $id) {
    $assignment = $this->assignments->findOrThrow($id);
    $user = $this->getCurrentUser();

    if (!$this->assignmentAcl->canViewDetail($assignment)) {
      throw new ForbiddenRequestException("You cannot view this assignment.");
    }

    $this->sendSuccessResponse($assignment);
  }

  /**
   * Update details of an assignment
   * @POST
   * @Param(type="post", name="version", validation="numericint", description="Version of the edited exercise")
   * @Param(type="post", name="isPublic", validation="bool", description="Is the assignment ready to be displayed to students?")
   * @Param(type="post", name="localizedTexts", validation="array", description="A description of the assignment")
   * @Param(type="post", name="firstDeadline", validation="numericint", description="First deadline for submission of the assignment")
   * @Param(type="post", name="maxPointsBeforeFirstDeadline", validation="numericint", description="A maximum of points that can be awarded for a submission before first deadline")
   * @Param(type="post", name="submissionsCountLimit", validation="numericint", description="A maximum amount of submissions by a student for the assignment")
   * @Param(type="post", name="scoreConfig", validation="string", description="A configuration of the score calculator (the exact format depends on the calculator assigned to the exercise)")
   * @Param(type="post", name="allowSecondDeadline", validation="bool", description="Should there be a second deadline for students who didn't make the first one?")
   * @Param(type="post", name="canViewLimitRatios", validation="bool", description="Can user view ratio of his solution memory and time usages and assignment limits?")
   * @Param(type="post", name="secondDeadline", validation="numericint", required=false, description="A second deadline for submission of the assignment (with different point award)")
   * @Param(type="post", name="maxPointsBeforeSecondDeadline", validation="numericint", required=false, description="A maximum of points that can be awarded for a late submission")
   * @Param(type="post", name="isBonus", validation="bool", description="If set to true then points from this exercise will not be included in overall score of group")
   * @Param(type="post", name="pointsPercentualThreshold", validation="numericint", required=FALSE, description="A minimum percentage of points needed to gain point from assignment")
   * @param string $id Identifier of the updated assignment
   * @throws BadRequestException
   * @throws ForbiddenRequestException
   * @throws InvalidArgumentException
   */
  public function actionUpdateDetail(string $id) {
    $assignment = $this->assignments->findOrThrow($id);
    if (!$this->assignmentAcl->canUpdate($assignment)) {
      throw new ForbiddenRequestException("You cannot update this assignment.");
    }

    $req = $this->getRequest();
    $version = intval($req->getPost("version"));
    if ($version !== $assignment->getVersion()) {
      throw new BadRequestException("The assignment was edited in the meantime and the version has changed. Current version is {$assignment->getVersion()}."); // @todo better exception
    }

    $wasPublic = $assignment->isPublic();
    $isPublic = filter_var($req->getPost("isPublic"), FILTER_VALIDATE_BOOLEAN);

    $assignment->incrementVersion();
    $assignment->setUpdatedAt(new \DateTime);
    $assignment->setIsPublic($isPublic);
    $assignment->setFirstDeadline(DateTime::createFromFormat('U', $req->getPost("firstDeadline")));
    $assignment->setSecondDeadline(DateTime::createFromFormat('U', $req->getPost("secondDeadline") ?: 0));
    $assignment->setMaxPointsBeforeFirstDeadline($req->getPost("maxPointsBeforeFirstDeadline"));
    $assignment->setMaxPointsBeforeSecondDeadline($req->getPost("maxPointsBeforeSecondDeadline") ?: 0);
    $assignment->setSubmissionsCountLimit($req->getPost("submissionsCountLimit"));
    $assignment->setScoreConfig($req->getPost("scoreConfig"));
    $assignment->setAllowSecondDeadline(filter_var($req->getPost("allowSecondDeadline"), FILTER_VALIDATE_BOOLEAN));
    $assignment->setCanViewLimitRatios(filter_var($req->getPost("canViewLimitRatios"), FILTER_VALIDATE_BOOLEAN));
    $assignment->setIsBonus(filter_var($req->getPost("isBonus"), FILTER_VALIDATE_BOOLEAN));
    $threshold = $req->getPost("pointsPercentualThreshold") !== NULL ? $req->getPost("pointsPercentualThreshold") / 100 : $assignment->getPointsPercentualThreshold();
    $assignment->setPointsPercentualThreshold($threshold);

    if ($wasPublic === false && $isPublic === true) {
      // assignment is moving from non-public to public, send notification to students
      $this->assignmentEmailsSender->assignmentCreated($assignment);
    }

    // retrieve localizations and prepare some temp variables
    $localizedTexts = $req->getPost("localizedTexts");
    $localizations = [];

    // localized texts cannot be empty
    if (count($localizedTexts) == 0) {
      throw new InvalidArgumentException("No entry for localized texts given.");
    }

    // go through given localizations and construct database entities
    foreach ($localizedTexts as $localization) {
      $lang = $localization["locale"];

      if (array_key_exists($lang, $localizations)) {
        throw new InvalidArgumentException("Duplicate entry for language $lang");
      }

      // create all new localized texts
      $localized = new LocalizedExercise(
        $lang,
        $localization["name"],
        $localization["text"],
        "",
        $assignment->getLocalizedTextByLocale($lang)
      );

      $localizations[$lang] = $localized;
    }

    // make changes to database
    $this->assignments->replaceLocalizedTexts($assignment, $localizations, FALSE);
    $this->assignments->flush();
    $this->sendSuccessResponse($assignment);
  }

  /**
   * Check if the version of the assignment is up-to-date.
   * @POST
   * @Param(type="post", name="version", validation="numericint", description="Version of the assignment.")
   * @param string $id Identifier of the assignment
   * @throws ForbiddenRequestException
   */
  public function actionValidate($id) {
    $assignment = $this->assignments->findOrThrow($id);
    if (!$this->assignmentAcl->canUpdate($assignment)) {
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
   * @Param(type="post", name="exerciseId", description="Identifier of the exercise")
   * @Param(type="post", name="groupId", description="Identifier of the group")
   */
  public function actionCreate() {
    $req = $this->getRequest();
    $exerciseId = $req->getPost("exerciseId");
    $groupId = $req->getPost("groupId");

    $group = $this->groups->findOrThrow($groupId);
    $exercise = $this->exercises->findOrThrow($exerciseId);

    if ($exercise->isLocked()) {
      throw new InvalidArgumentException("Exercise '$exerciseId' is locked");
    }

    if (!$this->groupAcl->canAssignExercise($group, $exercise)) {
      throw new ForbiddenRequestException("You are not allowed to assign exercises to group '$groupId'.");
    }

    if ($exercise->getReferenceSolutions()->isEmpty()) {
      throw new InvalidArgumentException("Exercise '$exerciseId' does not have any reference solutions");
    }

    // create an assignment for the group based on the given exercise but without any params
    // and make sure the assignment is not public yet - the supervisor must edit it first
    $assignment = Assignment::assignToGroup($exercise, $group, FALSE);
    $assignment->setScoreConfig($this->getDefaultScoreConfig($assignment));
    $this->assignments->persist($assignment);
    $this->sendSuccessResponse($assignment);
  }

  private function getDefaultScoreConfig(Assignment $assignment): string {
    if (count($assignment->getRuntimeEnvironments()) === 0) {
      throw new InvalidStateException("Assignment has no runtime configurations");
    }

    $exerciseConfig = $this->exerciseConfigLoader->loadExerciseConfig($assignment->getExerciseConfig()->getParsedConfig());
    $tests = array_keys($exerciseConfig->getTests());
    return $this->calculators->getDefaultCalculator()->getDefaultConfig($tests);
  }

  /**
   * Delete an assignment
   * @DELETE
   * @param string $id Identifier of the assignment to be removed
   * @throws ForbiddenRequestException
   */
  public function actionRemove(string $id) {
    $assignment = $this->assignments->findOrThrow($id);

    if (!$this->assignmentAcl->canRemove($assignment)) {
      throw new ForbiddenRequestException("You cannot remove this assignment.");
    }

    $this->assignments->remove($assignment);
    $this->sendSuccessResponse("OK");
  }

  /**
   * Update the assignment so that it matches with the current version of the exercise (limits, texts, etc.)
   * @param string $id Identifier of the exercise that should be synchronized
   * @POST
   * @throws ForbiddenRequestException
   * @throws BadRequestException
   */
  public function actionSyncWithExercise($id) {
    $assignment = $this->assignments->findOrThrow($id);

    if (!$this->assignmentAcl->canUpdate($assignment)) {
      throw new ForbiddenRequestException("You cannot sync this assignment.");
    }

    $assignment->syncWithExercise();
    $this->assignments->flush();
    $this->sendSuccessResponse($assignment);
  }

  /**
   * Get a list of solutions submitted by a user of an assignment
   * @GET
   * @param string $id Identifier of the assignment
   * @param string $userId Identifier of the user
   * @throws ForbiddenRequestException
   */
  public function actionSubmissions(string $id, string $userId) {
    $assignment = $this->assignments->findOrThrow($id);
    $user = $this->users->findOrThrow($userId);

    if (!$this->assignmentAcl->canViewSubmissions($assignment, $user)) {
      throw new ForbiddenRequestException();
    }

    $submissions = array_filter($this->submissions->findSubmissions($assignment, $userId), function (Submission $submission) {
      return $this->submissionAcl->canViewDetail($submission);
    });
    $submissions = array_map(function (Submission $submission) {
      $canViewDetails = $this->submissionAcl->canViewEvaluationDetails($submission);
      $canViewValues = $this->submissionAcl->canViewEvaluationValues($submission);
      return $submission->getData($canViewDetails, $canViewValues);
    }, $submissions);

    $this->sendSuccessResponse($submissions);
  }

  /**
   * Get the best solution by a user to an assignment
   * @GET
   * @param string $id Identifier of the assignment
   * @param string $userId Identifier of the user
   * @throws ForbiddenRequestException
   */
  public function actionBestSubmission(string $id, string $userId) {
    $assignment = $this->assignments->findOrThrow($id);
    $user = $this->users->findOrThrow($userId);
    $submission = $assignment->getBestSolution($user);

    if ($submission == NULL) {
      $this->sendSuccessResponse(NULL);
    }
    if (!$this->assignmentAcl->canViewSubmissions($assignment, $user) ||
        !$this->submissionAcl->canViewDetail($submission)) {
      throw new ForbiddenRequestException();
    }

    $canViewDetails = $this->submissionAcl->canViewEvaluationDetails($submission);
    $canViewValues = $this->submissionAcl->canViewEvaluationValues($submission);
    $this->sendSuccessResponse($submission->getData($canViewDetails, $canViewValues));
  }

  /**
   * Get the best solutions to an assignment for all students in group.
   * @GET
   * @param string $id Identifier of the assignment
   * @throws ForbiddenRequestException
   */
  public function actionBestSubmissions(string $id) {
    $assignment = $this->assignments->findOrThrow($id);
    if (!$this->assignmentAcl->canViewDetail($assignment)) {
      throw new ForbiddenRequestException();
    }

    $bestSubmissions = [];
    foreach ($assignment->getGroup()->getStudents() as $student) {
      $submission = $assignment->getBestSolution($student);
      if ($submission === null) {
        $bestSubmissions[$student->getId()] = null;
        continue;
      }

      if (!$this->assignmentAcl->canViewSubmissions($assignment, $student) ||
          !$this->submissionAcl->canViewDetail($submission)) {
        continue;
      }

      $canViewDetails = $this->submissionAcl->canViewEvaluationDetails($submission);
      $canViewValues = $this->submissionAcl->canViewEvaluationValues($submission);
      $bestSubmissions[$student->getId()] = $submission->getData($canViewDetails, $canViewValues);
    }

    $this->sendSuccessResponse($bestSubmissions);
  }

}
