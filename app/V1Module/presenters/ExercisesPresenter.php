<?php

namespace App\V1Module\Presenters;

use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Model\Entity\ExerciseConfig;
use App\Model\Entity\Pipeline;
use App\Model\Repository\Exercises;
use App\Model\Entity\Exercise;
use App\Model\Entity\LocalizedExercise;
use App\Model\Repository\HardwareGroups;
use App\Model\Repository\Groups;
use App\Security\ACL\IExercisePermissions;
use App\Security\ACL\IGroupPermissions;
use App\Security\ACL\IPipelinePermissions;
use Exception;

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
   * Get a list of exercises with an optional filter
   * @GET
   * @param string $search text which will be searched in exercises names
   * @throws ForbiddenRequestException
   */
  public function actionDefault(string $search = NULL) {
    if (!$this->exerciseAcl->canViewAll()) {
      throw new ForbiddenRequestException();
    }

    $exercises = $this->exercises->searchByName($search);
    $exercises = array_filter($exercises, function (Exercise $exercise) {
      return $this->exerciseAcl->canViewDetail($exercise);
    });
    $this->sendSuccessResponse(array_values($exercises));
  }

  /**
   * Get details of an exercise
   * @GET
   * @param string $id identification of exercise
   * @throws ForbiddenRequestException
   */
  public function actionDetail(string $id) {
    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canViewDetail($exercise)) {
      throw new ForbiddenRequestException();
    }

    $this->sendSuccessResponse($exercise);
  }

  /**
   * Update detail of an exercise
   * @POST
   * @param string $id identification of exercise
   * @throws BadRequestException
   * @throws ForbiddenRequestException
   * @throws InvalidArgumentException
   * @Param(type="post", name="name", validation="string:2..", description="Name of exercise")
   * @Param(type="post", name="version", validation="numericint", description="Version of the edited exercise")
   * @Param(type="post", name="description", validation="string:1..", description="Some brief description of this exercise for supervisors")
   * @Param(type="post", name="difficulty", description="Difficulty of an exercise, should be one of 'easy', 'medium' or 'hard'")
   * @Param(type="post", name="localizedTexts", validation="array", description="A description of the exercise")
   * @Param(type="post", name="isPublic", description="Exercise can be public or private", validation="bool", required=FALSE)
   * @Param(type="post", name="isLocked", description="If true, the exercise cannot be assigned", validation="bool", required=FALSE)
   * @throws BadRequestException
   * @throws InvalidArgumentException
   */
  public function actionUpdateDetail(string $id) {
    $req = $this->getRequest();
    $name = $req->getPost("name");
    $difficulty = $req->getPost("difficulty");
    $isPublic = filter_var($req->getPost("isPublic"), FILTER_VALIDATE_BOOLEAN);
    $isLocked = filter_var($req->getPost("isLocked"), FILTER_VALIDATE_BOOLEAN);
    $description = $req->getPost("description");

    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canUpdate($exercise)) {
      throw new ForbiddenRequestException("You cannot update this exercise.");
    }

    $version = intval($req->getPost("version"));
    if ($version !== $exercise->getVersion()) {
      throw new BadRequestException("The exercise was edited in the meantime and the version has changed. Current version is {$exercise->getVersion()}."); // @todo better exception
    }

    // make changes to newly created exercise
    $exercise->setName($name);
    $exercise->setDifficulty($difficulty);
    $exercise->setIsPublic($isPublic);
    $exercise->setUpdatedAt(new \DateTime);
    $exercise->incrementVersion();
    $exercise->setDescription($description);
    $exercise->setLocked($isLocked);

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
        $localization["text"],
        $lang,
        isset($localization["shortText"]) ? $localization["shortText"] : NULL,
        $exercise->getLocalizedTextByLocale($lang)
      );

      $localizations[$lang] = $localized;
    }

    // make changes to database
    $this->exercises->replaceLocalizedTexts($exercise, array_values($localizations), FALSE);
    $this->exercises->flush();
    $this->sendSuccessResponse($exercise);
  }

  /**
   * Check if the version of the exercise is up-to-date.
   * @POST
   * @Param(type="post", name="version", validation="numericint", description="Version of the exercise.")
   * @param string $id Identifier of the exercise
   * @throws ForbiddenRequestException
   */
  public function actionValidate($id) {
    $exercise = $this->exercises->findOrThrow($id);

    if (!$this->exerciseAcl->canUpdate($exercise)) {
      throw new ForbiddenRequestException("You cannot modify this exercise.");
    }

    $req = $this->getHttpRequest();
    $version = intval($req->getPost("version"));

    $this->sendSuccessResponse([
      "versionIsUpToDate" => $exercise->getVersion() === $version
    ]);
  }

  /**
   * Get all pipelines for an exercise.
   * @GET
   * @param string $id Identifier of the exercise
   * @throws ForbiddenRequestException
   */
  public function actionGetPipelines(string $id) {
    $exercise = $this->exercises->findOrThrow($id);

    if (!$this->exerciseAcl->canViewPipelines($exercise)) {
      throw new ForbiddenRequestException();
    }

    $pipelines = $exercise->getPipelines()->filter(function (Pipeline $pipeline) {
      return $this->pipelineAcl->canViewDetail($pipeline);
    })->getValues();
    $this->sendSuccessResponse($pipelines);
  }

  /**
   * Create exercise with all default values.
   * Exercise detail can be then changed in appropriate endpoint.
   * @POST
   * @Param(type="post", name="groupId", required=FALSE, description="Identifier of the group to which exercise belongs to")
   */
  public function actionCreate() {
    $user = $this->getCurrentUser();

    $group = NULL;
    if ($this->getRequest()->getPost("groupId")) {
      $group = $this->groups->findOrThrow($this->getRequest()->getPost("groupId"));
    }

    if (!$this->exerciseAcl->canCreate() || ($group && !$this->groupAcl->canCreateExercise($group))) {
      throw new ForbiddenRequestException();
    }

    // create exercise and fill some predefined details
    $exercise = Exercise::create($user, $group);
    $exercise->setName("Exercise by " . $user->getName());

    // create and store basic exercise configuration
    $exerciseConfig = new ExerciseConfig((string) new \App\Helpers\ExerciseConfig\ExerciseConfig(), $user);
    $exercise->setExerciseConfig($exerciseConfig);

    // set all hardware groups from the system to exercise
    // TODO: not quite good solution, automatically assign hwgroups, but sufficient for now
    foreach ($this->hardwareGroups->findAll() as $hardwareGroup) {
      $exercise->addHardwareGroup($hardwareGroup);
    }

    // and finally make changes to database
    $this->exercises->persist($exercise);
    $this->sendSuccessResponse($exercise);
  }

  /**
   * Delete an exercise
   * @DELETE
   * @param string $id
   * @throws ForbiddenRequestException
   */
  public function actionRemove(string $id) {
    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canRemove($exercise)) {
      throw new ForbiddenRequestException("You are not allowed to remove this exercise.");
    }

    $this->exercises->remove($exercise);
    $this->sendSuccessResponse("OK");
  }

  /**
   * Fork exercise from given one into the completely new one.
   * @POST
   * @param string $id Identifier of the exercise
   * @throws ForbiddenRequestException
   */
  public function actionForkFrom(string $id) {
    $user = $this->getCurrentUser();
    $forkFrom = $this->exercises->findOrThrow($id);

    if (!$this->exerciseAcl->canFork($forkFrom)) {
      throw new ForbiddenRequestException("Exercise cannot be forked by you");
    }

    $exercise = Exercise::forkFrom($forkFrom, $user);
    $this->exercises->persist($exercise);
    $this->sendSuccessResponse($exercise);
  }

}
