<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ApiException;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ParseException;
use App\Helpers\ExerciseConfig\Compiler;
use App\Helpers\ExerciseConfig\ExerciseConfigChecker;
use App\Helpers\ExerciseConfig\Updater;
use App\Helpers\Localizations;
use App\Helpers\ScoreCalculatorAccessor;
use App\Helpers\Validators;
use App\Model\Entity\ExerciseConfig;
use App\Model\Entity\Pipeline;
use App\Model\Repository\Exercises;
use App\Model\Entity\Exercise;
use App\Model\Entity\LocalizedExercise;
use App\Model\Repository\HardwareGroups;
use App\Model\Repository\Groups;
use App\Model\View\ExerciseViewFactory;
use App\Security\ACL\IExercisePermissions;
use App\Security\ACL\IGroupPermissions;
use App\Security\ACL\IPipelinePermissions;
use Nette\Utils\Arrays;

/**
 * Endpoints for exercise manipulation
 * @LoggedIn
 */

class ExercisesPresenter extends BasePresenter {

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
   * @var HardwareGroups
   * @inject
   */
  public $hardwareGroups;

  /**
   * @var IExercisePermissions
   * @inject
   */
  public $exerciseAcl;

  /**
   * @var IGroupPermissions
   * @inject
   */
  public $groupAcl;

  /**
   * @var IPipelinePermissions
   * @inject
   */
  public $pipelineAcl;

  /**
   * @var ScoreCalculatorAccessor
   * @inject
   */
  public $calculators;

  /**
   * @var Updater
   * @inject
   */
  public $exerciseConfigUpdater;

  /**
   * @var ExerciseConfigChecker
   * @inject
   */
  public $configChecker;

  /**
   * @var ExerciseViewFactory
   * @inject
   */
  public $exerciseViewFactory;


  public function checkDefault(string $search = null) {
    if (!$this->exerciseAcl->canViewAll()) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Get a list of exercises with an optional filter
   * @GET
   * @param string $search text which will be searched in exercises names
   */
  public function actionDefault(string $search = null) {
    $exercises = $this->exercises->searchByName($search);
    $exercises = array_filter($exercises, function (Exercise $exercise) {
      return $this->exerciseAcl->canViewDetail($exercise);
    });
    $this->sendSuccessResponse(array_map([$this->exerciseViewFactory, "getExercise"], array_values($exercises)));
  }

  public function checkListByIds() {
    if (!$this->exerciseAcl->canViewList()) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Get a list of exercises based on given ids.
   * @POST
   * @Param(type="post", name="ids", validation="array", description="Identifications of exercises")
   */
  public function actionListByIds() {
    $exercises = $this->exercises->findByIds($this->getRequest()->getPost("ids"));
    $exercises = array_filter($exercises, function (Exercise $exercise) {
      return $this->exerciseAcl->canViewDetail($exercise);
    });
    $this->sendSuccessResponse(array_map([$this->exerciseViewFactory, "getExercise"], array_values($exercises)));
  }

  public function checkDetail(string $id) {
    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canViewDetail($exercise)) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Get details of an exercise
   * @GET
   * @param string $id identification of exercise
   */
  public function actionDetail(string $id) {
    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOrThrow($id);
    $this->sendSuccessResponse($this->exerciseViewFactory->getExercise($exercise));
  }

  public function checkUpdateDetail(string $id) {
    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canUpdate($exercise)) {
      throw new ForbiddenRequestException("You cannot update this exercise.");
    }
  }

  /**
   * Update detail of an exercise
   * @POST
   * @param string $id identification of exercise
   * @throws BadRequestException
   * @throws ForbiddenRequestException
   * @throws InvalidArgumentException
   * @Param(type="post", name="version", validation="numericint", description="Version of the edited exercise")
   * @Param(type="post", name="difficulty", description="Difficulty of an exercise, should be one of 'easy', 'medium' or 'hard'")
   * @Param(type="post", name="localizedTexts", validation="array", description="A description of the exercise")
   * @Param(type="post", name="isPublic", description="Exercise can be public or private", validation="bool", required=false)
   * @Param(type="post", name="isLocked", description="If true, the exercise cannot be assigned", validation="bool", required=false)
   * @Param(type="post", name="configurationType", description="Identifies the way the evaluation of the exercise is configured", validation="string", required=false)
   * @throws BadRequestException
   * @throws InvalidArgumentException
   */
  public function actionUpdateDetail(string $id) {
    $req = $this->getRequest();
    $difficulty = $req->getPost("difficulty");
    $isPublic = filter_var($req->getPost("isPublic"), FILTER_VALIDATE_BOOLEAN);
    $isLocked = filter_var($req->getPost("isLocked"), FILTER_VALIDATE_BOOLEAN);

    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOrThrow($id);

    $version = intval($req->getPost("version"));
    if ($version !== $exercise->getVersion()) {
      throw new BadRequestException("The exercise was edited in the meantime and the version has changed. Current version is {$exercise->getVersion()}."); // @todo better exception
    }

    // make changes to newly created exercise
    $exercise->setDifficulty($difficulty);
    $exercise->setIsPublic($isPublic);
    $exercise->updatedNow();
    $exercise->incrementVersion();
    $exercise->setLocked($isLocked);

    $configurationType = $req->getPost("configurationType");
    if ($configurationType) {
      if (!Compiler::checkConfigurationType($configurationType)) {
        throw new InvalidArgumentException("Invalid configuration type '{$configurationType}'");
      }
      $exercise->setConfigurationType($configurationType);
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
      if (!array_key_exists("locale", $localization) || !array_key_exists("name", $localization) || !array_key_exists("text", $localization)) {
        throw new InvalidArgumentException("Malformed localized text entry");
      }

      $lang = $localization["locale"];

      if (array_key_exists($lang, $localizations)) {
        throw new InvalidArgumentException("Duplicate entry for language $lang");
      }

      // create all new localized texts
      $externalAssignmentLink = Arrays::get($localization, "link", "");
      if ($externalAssignmentLink !== "" && !Validators::isUrl($externalAssignmentLink)) {
        throw new InvalidArgumentException("External assignment link is not a valid URL");
      }

      $localization["description"] = $localization["description"] ?? "";

      $localized = new LocalizedExercise(
        $lang, $localization["name"], $localization["text"], $localization["description"], $externalAssignmentLink ?: null
      );

      $localizations[$lang] = $localized;
    }

    // make changes to database
    Localizations::updateCollection($exercise->getLocalizedTexts(), $localizations);

    foreach ($exercise->getLocalizedTexts() as $localizedText) {
      $this->exercises->persist($localizedText, false);
    }

    $this->exercises->flush();

    $this->configChecker->check($exercise);
    $this->exercises->flush();

    $this->sendSuccessResponse($this->exerciseViewFactory->getExercise($exercise));
  }

  public function checkValidate($id) {
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canUpdate($exercise)) {
      throw new ForbiddenRequestException("You cannot modify this exercise.");
    }
  }

  /**
   * Check if the version of the exercise is up-to-date.
   * @POST
   * @Param(type="post", name="version", validation="numericint", description="Version of the exercise.")
   * @param string $id Identifier of the exercise
   */
  public function actionValidate($id) {
    $exercise = $this->exercises->findOrThrow($id);

    $req = $this->getHttpRequest();
    $version = intval($req->getPost("version"));

    $this->sendSuccessResponse([
      "versionIsUpToDate" => $exercise->getVersion() === $version
    ]);
  }

  public function checkGetPipelines(string $id) {
    $exercise = $this->exercises->findOrThrow($id);

    if (!$this->exerciseAcl->canViewPipelines($exercise)) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Get all pipelines for an exercise.
   * @GET
   * @param string $id Identifier of the exercise
   */
  public function actionGetPipelines(string $id) {
    $exercise = $this->exercises->findOrThrow($id);

    $pipelines = $exercise->getPipelines()->filter(function (Pipeline $pipeline) {
      return $this->pipelineAcl->canViewDetail($pipeline);
    })->getValues();
    $this->sendSuccessResponse($pipelines);
  }

  /**
   * Create exercise with all default values.
   * Exercise detail can be then changed in appropriate endpoint.
   * @POST
   * @Param(type="post", name="groupId", description="Identifier of the group to which exercise belongs to")
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   * @throws ApiException
   * @throws ParseException
   */
  public function actionCreate() {
    $user = $this->getCurrentUser();
    $group = $this->groups->findOrThrow($this->getRequest()->getPost("groupId"));
    if (!$this->groupAcl->canCreateExercise($group)) {
      throw new ForbiddenRequestException();
    }

    // create exercise and fill some predefined details
    $exercise = Exercise::create($user, $group);
    $localizedExercise = new LocalizedExercise(
      $user->getSettings()->getDefaultLanguage(), "Exercise by " . $user->getName(), "", "", null
    );
    $this->exercises->persist($localizedExercise, false);
    $exercise->addLocalizedText($localizedExercise);

    // create and store basic exercise configuration
    $exerciseConfig = new ExerciseConfig((string) new \App\Helpers\ExerciseConfig\ExerciseConfig(), $user);
    $exercise->setExerciseConfig($exerciseConfig);

    // create default score configuration without tests
    $scoreConfig = $this->calculators->getDefaultCalculator()->getDefaultConfig([]);
    $exercise->setScoreConfig($scoreConfig);

    // and finally make changes to database
    $this->exercises->persist($exercise);

    $this->configChecker->check($exercise);
    $this->exercises->flush();

    $this->sendSuccessResponse($this->exerciseViewFactory->getExercise($exercise));
  }

  public function checkHardwareGroups(string $id) {
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canUpdate($exercise)) {
      throw new ForbiddenRequestException("You cannot modify this exercise.");
    }
  }

  /**
   * Set hardware groups which are associated with exercise.
   * @POST
   * @param string $id identifier of exercise
   * @Param(type="post", name="hwGroups", validation="array", description="List of hardware groups identifications to which exercise belongs to")
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   */
  public function actionHardwareGroups(string $id) {
    $exercise = $this->exercises->findOrThrow($id);

    // load all new hardware groups
    $req = $this->getRequest();
    $groups = [];
    foreach ($req->getPost("hwGroups") as $groupId) {
      $groups[] = $this->hardwareGroups->findOrThrow($groupId);
    }

    // ... and after clearing the old ones assign new ones
    $exercise->getHardwareGroups()->clear();
    foreach ($groups as $group) {
      $exercise->addHardwareGroup($group);
    }

    // update configurations
    $this->exerciseConfigUpdater->hwGroupsUpdated($exercise, $this->getCurrentUser(), false);

    // update and return
    $exercise->updatedNow();
    $this->exercises->flush();

    // check exercise configuration
    $this->configChecker->check($exercise);
    $this->exercises->flush();
    $this->sendSuccessResponse($this->exerciseViewFactory->getExercise($exercise));
  }

  public function checkRemove(string $id) {
    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canRemove($exercise)) {
      throw new ForbiddenRequestException("You are not allowed to remove this exercise.");
    }
  }

  /**
   * Delete an exercise
   * @DELETE
   * @param string $id
   */
  public function actionRemove(string $id) {
    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOrThrow($id);

    $this->exercises->remove($exercise);
    $this->sendSuccessResponse("OK");
  }

  /**
   * Fork exercise from given one into the completely new one.
   * @POST
   * @param string $id Identifier of the exercise
   * @Param(type="post", name="groupId", description="Identifier of the group to which exercise will be forked")
   * @throws ApiException
   * @throws ForbiddenRequestException
   * @throws NotFoundException
   * @throws ParseException
   */
  public function actionForkFrom(string $id) {
    $user = $this->getCurrentUser();
    $forkFrom = $this->exercises->findOrThrow($id);

    $group = $this->groups->findOrThrow($this->getRequest()->getPost("groupId"));
    if (!$this->exerciseAcl->canFork($forkFrom) ||
        !$this->groupAcl->canCreateExercise($group)) {
      throw new ForbiddenRequestException("Exercise cannot be forked");
    }

    $exercise = Exercise::forkFrom($forkFrom, $user, $group);
    $this->exercises->persist($exercise);

    $this->configChecker->check($exercise);
    $this->exercises->flush();

    $this->sendSuccessResponse($this->exerciseViewFactory->getExercise($exercise));
  }

  public function checkAttachGroup(string $id, string $groupId) {
    $exercise = $this->exercises->findOrThrow($id);
    $group = $this->groups->findOrThrow($groupId);
    if (!$this->exerciseAcl->canAttachGroup($exercise, $group)) {
      throw new ForbiddenRequestException("You are not allowed to attach the group to the exercise");
    }
  }

  /**
   * Attach exercise to group with given identification.
   * @POST
   * @param string $id Identifier of the exercise
   * @param string $groupId Identifier of the group to which exercise should be attached
   * @throws InvalidArgumentException
   */
  public function actionAttachGroup(string $id, string $groupId) {
    $exercise = $this->exercises->findOrThrow($id);
    $group = $this->groups->findOrThrow($groupId);

    if ($exercise->getGroups()->contains($group)) {
      throw new InvalidArgumentException("groupId", "group is already attached to the exercise");
    }

    $exercise->addGroup($group);
    $this->exercises->flush();
    $this->sendSuccessResponse($this->exerciseViewFactory->getExercise($exercise));
  }

  public function checkDetachGroup(string $id, string $groupId) {
    $exercise = $this->exercises->findOrThrow($id);
    $group = $this->groups->findOrThrow($groupId);
    if (!$this->exerciseAcl->canDetachGroup($exercise, $group)) {
      throw new ForbiddenRequestException("You are not allowed to detach the group to the exercise");
    }

    if ($exercise->getGroups()->count() < 2) {
      throw new BadRequestException("You cannot detach last group from exercise");
    }
  }

  /**
   * Detach exercise from given group.
   * @DELETE
   * @param string $id Identifier of the exercise
   * @param string $groupId Identifier of the group which should be detached from exercise
   * @throws InvalidArgumentException
   */
  public function actionDetachGroup(string $id, string $groupId) {
    $exercise = $this->exercises->findOrThrow($id);
    $group = $this->groups->findOrThrow($groupId);

    if (!$exercise->getGroups()->contains($group)) {
      throw new InvalidArgumentException("groupId", "given group is not associated with exercise");
    }

    $exercise->removeGroup($group);
    $this->exercises->flush();
    $this->sendSuccessResponse($this->exerciseViewFactory->getExercise($exercise));
  }

}
