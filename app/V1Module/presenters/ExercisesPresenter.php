<?php

namespace App\V1Module\Presenters;

use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\CannotReceiveUploadedFileException;
use App\Helpers\UploadedFileStorage;
use App\Model\Entity\ExerciseConfig;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\AdditionalExerciseFile;
use App\Model\Repository\Exercises;
use App\Model\Entity\Exercise;
use App\Helpers\ExerciseFileStorage;
use App\Model\Entity\LocalizedText;
use App\Model\Repository\UploadedFiles;
use App\Model\Repository\Groups;
use App\Security\ACL\IExercisePermissions;
use App\Security\ACL\IGroupPermissions;
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
   * @var UploadedFiles
   * @inject
   */
  public $uploadedFiles;

  /**
   * @var ExerciseFileStorage
   * @inject
   */
  public $supplementaryFileStorage;

  /**
   * @var UploadedFileStorage
   * @inject
   */
  public $uploadedFileStorage;

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
    $this->sendSuccessResponse($exercises);
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
   * @Param(type="post", name="name", description="Name of exercise")
   * @Param(type="post", name="version", description="Version of the edited exercise")
   * @Param(type="post", name="description", description="Some brief description of this exercise for supervisors")
   * @Param(type="post", name="difficulty", description="Difficulty of an exercise, should be one of 'easy', 'medium' or 'hard'")
   * @Param(type="post", name="localizedTexts", validation="array", description="A description of the exercise")
   * @Param(type="post", name="isPublic", description="Exercise can be public or private", validation="bool", required=FALSE)
   * @throws BadRequestException
   * @throws InvalidArgumentException
   */
  public function actionUpdateDetail(string $id) {
    $req = $this->getRequest();
    $name = $req->getPost("name");
    $difficulty = $req->getPost("difficulty");
    $isPublic = filter_var($req->getPost("isPublic"), FILTER_VALIDATE_BOOLEAN);
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
      $localized = new LocalizedText(
        $localization["text"],
        $lang,
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
      throw new ForbiddenRequestException("You cannot modify this assignment.");
    }

    $req = $this->getHttpRequest();
    $version = intval($req->getPost("version"));

    $this->sendSuccessResponse([
      "versionIsUpToDate" => $exercise->getVersion() === $version
    ]);
  }

  /**
   * Associate supplementary files with an exercise and upload them to remote file server
   * @POST
   * @Param(type="post", name="files", description="Identifiers of supplementary files")
   * @param string $id identification of exercise
   * @throws BadRequestException
   * @throws CannotReceiveUploadedFileException
   * @throws ForbiddenRequestException
   */
  public function actionUploadSupplementaryFiles(string $id) {
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canUpdate($exercise)) {
      throw new ForbiddenRequestException("You cannot update this exercise.");
    }

    $files = $this->uploadedFiles->findAllById($this->getRequest()->getPost("files"));
    $supplementaryFiles = [];
    $deletedFiles = [];

    /** @var UploadedFile $file */
    foreach ($files as $file) {
      if (get_class($file) !== UploadedFile::class) {
        throw new ForbiddenRequestException("File {$file->getId()} was already used somewhere else");
      }

      $supplementaryFiles[] = $exerciseFile = $this->supplementaryFileStorage->store($file, $exercise);
      $this->uploadedFiles->persist($exerciseFile, FALSE);
      $this->uploadedFiles->remove($file, FALSE);
      $deletedFiles[] = $file;
    }

    $this->uploadedFiles->flush();

    /** @var UploadedFile $file */
    foreach ($deletedFiles as $file) {
      try {
        $this->uploadedFileStorage->delete($file);
      } catch (Exception $e) {} // TODO not worth aborting the request - log it?
    }

    $this->sendSuccessResponse($supplementaryFiles);
  }

  /**
   * Get list of all supplementary files for an exercise
   * @GET
   * @param string $id identification of exercise
   * @throws ForbiddenRequestException
   */
  public function actionGetSupplementaryFiles(string $id) {
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canViewDetail($exercise)) {
      throw new ForbiddenRequestException("You cannot view supplementary files for this exercise.");
    }

    $this->sendSuccessResponse($exercise->getSupplementaryEvaluationFiles()->getValues());
  }

  /**
   * Associate additional exercise files with an exercise
   * @POST
   * @Param(type="post", name="files", description="Identifiers of additional files")
   * @param string $id identification of exercise
   * @throws BadRequestException
   * @throws CannotReceiveUploadedFileException
   * @throws ForbiddenRequestException
   */
  public function actionUploadAdditionalFiles(string $id) {
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canUpdate($exercise)) {
      throw new ForbiddenRequestException("You cannot upload files for this exercise.");
    }

    $files = $this->uploadedFiles->findAllById($this->getRequest()->getPost("files"));
    $additionalFiles = [];

    /** @var UploadedFile $file */
    foreach ($files as $file) {
      if (get_class($file) !== UploadedFile::class) {
        throw new ForbiddenRequestException("File {$file->getId()} was already used somewhere else");
      }

      $additionalFiles[] = $exerciseFile = AdditionalExerciseFile::fromUploadedFile($file, $exercise);
      $this->uploadedFiles->persist($exerciseFile, FALSE);
      $this->uploadedFiles->remove($file, FALSE);
    }

    $this->uploadedFiles->flush();
    $this->sendSuccessResponse($additionalFiles);
  }

  /**
   * Get a list of all additional files for an exercise
   * @GET
   * @param string $id identification of exercise
   * @throws ForbiddenRequestException
   */
  public function actionGetAdditionalFiles(string $id) {
    /** @var Exercise $exercise */
    $exercise = $this->exercises->findOrThrow($id);
    if (!$this->exerciseAcl->canUpdate($exercise)) {
      throw new ForbiddenRequestException("You cannot view supplementary files for this exercise.");
    }

    $this->sendSuccessResponse($exercise->getAdditionalFiles()->getValues());
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
    $exerciseConfig = new ExerciseConfig((string) new \App\Helpers\ExerciseConfig\ExerciseConfig());
    $exercise->setExerciseConfig($exerciseConfig);

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
