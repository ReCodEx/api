<?php

namespace App\V1Module\Presenters;

use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\InvalidStateException;
use App\Exceptions\NotFoundException;
use App\Helpers\Localizations;
use App\Helpers\Notifications\AssignmentEmailsSender;
use App\Helpers\Validators;
use App\Model\Entity\LocalizedExercise;
use App\Model\Entity\ShadowAssignment;
use App\Model\Entity\ShadowAssignmentEvaluation;
use App\Model\Repository\Groups;
use App\Model\Repository\ShadowAssignments;
use App\Model\View\ShadowAssignmentEvaluationViewFactory;
use App\Model\View\ShadowAssignmentViewFactory;
use App\Security\ACL\IGroupPermissions;
use App\Security\ACL\IShadowAssignmentEvaluationPermissions;
use App\Security\ACL\IShadowAssignmentPermissions;
use Nette\Utils\Arrays;

/**
 * Endpoints for points assignment manipulation
 * @LoggedIn
 */
class ShadowAssignmentsPresenter extends BasePresenter {

  /**
   * @var ShadowAssignments
   * @inject
   */
  public $shadowAssignments;

  /**
   * @var IShadowAssignmentPermissions
   * @inject
   */
  public $shadowAssignmentAcl;

  /**
   * @var IShadowAssignmentEvaluationPermissions
   * @inject
   */
  public $shadowAssignmentEvaluationAcl;

  /**
   * @var ShadowAssignmentViewFactory
   * @inject
   */
  public $shadowAssignmentViewFactory;

  /**
   * @var ShadowAssignmentEvaluationViewFactory
   * @inject
   */
  public $shadowAssignmentEvaluationViewFactory;

  /**
   * @var AssignmentEmailsSender
   * @inject
   */
  public $assignmentEmailsSender;

  /**
   * @var Groups
   * @inject
   */
  public $groups;

  /**
   * @var IGroupPermissions
   * @inject
   */
  public $groupAcl;



  public function checkDetail(string $id) {
    $assignment = $this->shadowAssignments->findOrThrow($id);
    if (!$this->shadowAssignmentAcl->canViewDetail($assignment)) {
      throw new ForbiddenRequestException("You cannot view this shadow assignment.");
    }
  }

  /**
   * Get details of a shadow assignment
   * @GET
   * @param string $id Identifier of the assignment
   * @throws NotFoundException
   */
  public function actionDetail(string $id) {
    $assignment = $this->shadowAssignments->findOrThrow($id);
    $this->sendSuccessResponse($this->shadowAssignmentViewFactory->getAssignment($assignment));
  }

  public function checkUpdateDetail(string $id) {
    $assignment = $this->shadowAssignments->findOrThrow($id);
    if (!$this->shadowAssignmentAcl->canUpdate($assignment)) {
      throw new ForbiddenRequestException("You cannot update this shadow assignment.");
    }
  }

  /**
   * Update details of an shadow assignment
   * @POST
   * @param string $id Identifier of the updated assignment
   * @Param(type="post", name="version", validation="numericint", description="Version of the edited assignment")
   * @Param(type="post", name="isPublic", validation="bool", description="Is the assignment ready to be displayed to students?")
   * @Param(type="post", name="isBonus", validation="bool", description="If set to true then points from this exercise will not be included in overall score of group")
   * @Param(type="post", name="localizedTexts", validation="array", description="A description of the assignment")
   * @Param(type="post", name="maxPoints", validation="numericint", description="A maximum of points that user can be awarded")
   * @Param(type="post", name="sendNotification", required=false, validation="bool", description="If email notification should be sent")
   * @throws BadRequestException
   * @throws InvalidArgumentException
   * @throws NotFoundException
   */
  public function actionUpdateDetail(string $id) {
    $assignment = $this->shadowAssignments->findOrThrow($id);

    $req = $this->getRequest();
    $version = intval($req->getPost("version"));
    if ($version !== $assignment->getVersion()) {
      throw new BadRequestException("The shadow assignment was edited in the meantime and the version has changed. Current version is {$assignment->getVersion()}.");
    }

    // localized texts cannot be empty
    if (count($req->getPost("localizedTexts")) == 0) {
      throw new InvalidArgumentException("No entry for localized texts given.");
    }

    // old values of some attributes
    $wasPublic = $assignment->isPublic();
    $isPublic = filter_var($req->getPost("isPublic"), FILTER_VALIDATE_BOOLEAN);
    $sendNotification = $req->getPost("sendNotification") ? filter_var($req->getPost("sendNotification"), FILTER_VALIDATE_BOOLEAN) : true;

    $assignment->incrementVersion();
    $assignment->updatedNow();
    $assignment->setIsPublic($isPublic);
    $assignment->setIsBonus(filter_var($req->getPost("isBonus"), FILTER_VALIDATE_BOOLEAN));
    $assignment->setMaxPoints($req->getPost("maxPoints"));

    if ($sendNotification && $wasPublic === false && $isPublic === true) {
      // assignment is moving from non-public to public, send notification to students
      $this->assignmentEmailsSender->assignmentCreated($assignment);
    }

    // go through localizedTexts and construct database entities
    $localizedTexts = [];
    foreach ($req->getPost("localizedTexts") as $localization) {
      $lang = $localization["locale"];

      if (array_key_exists($lang, $localizedTexts)) {
        throw new InvalidArgumentException("Duplicate entry for language $lang in localizedTexts");
      }

      // create all new localized texts
      $localizedExercise = $assignment->getLocalizedTextByLocale($lang);
      $externalAssignmentLink = trim(Arrays::get($localization, "link", ""));
      if ($externalAssignmentLink !== "" && !Validators::isUrl($externalAssignmentLink)) {
        throw new InvalidArgumentException("External assignment link is not a valid URL");
      }

      $localized = new LocalizedExercise(
        $lang, $localization["name"], $localization["text"],
        $localizedExercise ? $localizedExercise->getDescription() : "",
        $externalAssignmentLink ?: null
      );

      $localizedTexts[$lang] = $localized;
    }

    // make changes to database
    Localizations::updateCollection($assignment->getLocalizedTexts(), $localizedTexts);

    foreach ($assignment->getLocalizedTexts() as $localizedText) {
      $this->shadowAssignments->persist($localizedText, false);
    }

    $this->shadowAssignments->flush();
    $this->sendSuccessResponse($this->shadowAssignmentViewFactory->getAssignment($assignment));
  }

  /**
   * Create new shadow assignment in given group.
   * @POST
   * @Param(type="post", name="groupId", description="Identifier of the group")
   * @throws ForbiddenRequestException
   * @throws BadRequestException
   * @throws NotFoundException
   */
  public function actionCreate() {
    $req = $this->getRequest();
    $group = $this->groups->findOrThrow($req->getPost("groupId"));

    if (!$this->groupAcl->canCreateShadowAssignment($group)) {
      throw new ForbiddenRequestException("You are not allowed to create assignment in given group.");
    }

    if ($group->isOrganizational()) {
      throw new BadRequestException("You cannot create assignment in organizational groups");
    }

    $assignment = ShadowAssignment::assignToGroup($group);
    $this->shadowAssignments->persist($assignment);
    $this->sendSuccessResponse($this->shadowAssignmentViewFactory->getAssignment($assignment));
  }

  public function checkRemove(string $id) {
    $assignment = $this->shadowAssignments->findOrThrow($id);

    if (!$this->shadowAssignmentAcl->canRemove($assignment)) {
      throw new ForbiddenRequestException("You cannot remove this shadow assignment.");
    }
  }

  /**
   * Delete shadow assignment
   * @DELETE
   * @param string $id Identifier of the assignment to be removed
   * @throws NotFoundException
   */
  public function actionRemove(string $id) {
    $assignment = $this->shadowAssignments->findOrThrow($id);
    $this->shadowAssignments->remove($assignment);
    $this->sendSuccessResponse("OK");
  }

  public function checkEvaluations(string $id) {
    $assignment = $this->shadowAssignments->findOrThrow($id);
    if (!$this->shadowAssignmentAcl->canViewEvaluations($assignment)) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Get a list of evaluations of all users for the assignment
   * @GET
   * @param string $id Identifier of the assignment
   * @throws NotFoundException
   */
  public function actionEvaluations(string $id) {
    $assignment = $this->shadowAssignments->findOrThrow($id);

    $evaluations = array_filter($assignment->getShadowAssignmentEvaluations()->getValues(),
      function (ShadowAssignmentEvaluation $evaluation) {
        return $this->shadowAssignmentEvaluationAcl->canViewDetail($evaluation);
      });

    $this->sendSuccessResponse($this->shadowAssignmentEvaluationViewFactory->getEvaluations($evaluations));
  }
}
