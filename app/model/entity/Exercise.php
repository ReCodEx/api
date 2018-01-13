<?php

namespace App\Model\Entity;

use App\Helpers\Evaluation\IExercise;
use App\Helpers\Localizations;
use \DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use Doctrine;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 *
 * @method string getId()
 * @method Collection getRuntimeEnvironments()
 * @method User getAuthor()
 * @method int getVersion()
 * @method void setScoreConfig(string $scoreConfig)
 * @method void setDifficulty(string $difficulty)
 * @method void setIsPublic(bool $isPublic)
 * @method void setExerciseConfig(ExerciseConfig $exerciseConfig)
 * @method setConfigurationType($type)
 */
class Exercise implements JsonSerializable, IExercise
{
  use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;
  use ExerciseData;
  use UpdateableEntity;
  use DeleteableEntity;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="integer")
   */
  protected $version;

  /**
   * Increment version number.
   */
  public function incrementVersion() {
    $this->version++;
  }

  /**
   * @ORM\Column(type="datetime")
   */
  protected $createdAt;

  /**
   * @ORM\Column(type="string")
   */
  protected $difficulty;

  /**
   * @ORM\Column(type="string", nullable=true)
   */
  protected $scoreCalculator;

  public function getScoreCalculator(): ?string {
    return $this->scoreCalculator;
  }

  /**
   * @ORM\Column(type="text")
   */
  protected $scoreConfig;

  public function getScoreConfig(): string {
    return $this->scoreConfig;
  }

  /**
   * @ORM\ManyToMany(targetEntity="RuntimeEnvironment")
   */
  protected $runtimeEnvironments;

  /**
   * @ORM\ManyToOne(targetEntity="Exercise")
   * @ORM\JoinColumn(name="exercise_id", referencedColumnName="id")
   */
  protected $exercise;

  public function getForkedFrom() {
      return $this->exercise;
  }

  /**
   * @ORM\OneToMany(targetEntity="ReferenceExerciseSolution", mappedBy="exercise")
   */
  protected $referenceSolutions;

  /**
   * @return Collection
   */
  public function getReferenceSolutions() {
    return $this->referenceSolutions->filter(function (ReferenceExerciseSolution $solution) {
      return $solution->getDeletedAt() === NULL;
    });
  }

  /**
   * @ORM\ManyToOne(targetEntity="User", inversedBy="exercises")
   */
  protected $author;

  public function isAuthor(User $user) {
    return $this->author->getId() === $user->getId();
  }

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isPublic;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isLocked;

  public function isPublic() {
    return $this->isPublic;
  }

  public function isLocked() {
    return $this->isLocked;
  }

  /**
   * @ORM\ManyToMany(targetEntity="Group", inversedBy="exercises")
   */
  protected $groups;

  /**
   * @return Collection
   */
  public function getGroups() {
    return $this->groups->filter(function (Group $group) {
      return $group->getDeletedAt() === NULL;
    });
  }

  /**
   * @ORM\OneToMany(targetEntity="Pipeline", mappedBy="exercise")
   */
  protected $pipelines;

  public function getPipelines() {
    return $this->pipelines->filter(function (Pipeline $pipeline) {
      return $pipeline->getDeletedAt() === NULL;
    });
  }

  /**
   * Constructor
   * @param $version
   * @param $difficulty
   * @param Collection $localizedTexts
   * @param Collection $runtimeEnvironments
   * @param Collection $hardwareGroups
   * @param Collection $supplementaryEvaluationFiles
   * @param Collection $attachmentFiles
   * @param Collection $exerciseLimits
   * @param Collection $exerciseEnvironmentConfigs
   * @param Collection $pipelines
   * @param Collection $exerciseTests
   * @param Collection $groups
   * @param Exercise|null $exercise
   * @param ExerciseConfig|null $exerciseConfig
   * @param User $user
   * @param bool $isPublic
   * @param bool $isLocked
   * @param string|null $scoreCalculator
   * @param string $scoreConfig
   * @param string $configurationType
   */
  private function __construct($version, $difficulty,
      Collection $localizedTexts, Collection $runtimeEnvironments,
      Collection $hardwareGroups, Collection $supplementaryEvaluationFiles,
      Collection $attachmentFiles, Collection $exerciseLimits,
      Collection $exerciseEnvironmentConfigs, Collection $pipelines,
      Collection $exerciseTests, Collection $groups = null, ?Exercise $exercise,
      ?ExerciseConfig $exerciseConfig = null, User $user, bool $isPublic = false,
      bool $isLocked = true, string $scoreCalculator = null,
      string $scoreConfig = "", string $configurationType = "simpleExerciseConfig") {
    $this->version = $version;
    $this->createdAt = new DateTime;
    $this->updatedAt = new DateTime;
    $this->localizedTexts = $localizedTexts;
    $this->difficulty = $difficulty;
    $this->runtimeEnvironments = $runtimeEnvironments;
    $this->exercise = $exercise;
    $this->author = $user;
    $this->supplementaryEvaluationFiles = $supplementaryEvaluationFiles;
    $this->isPublic = $isPublic;
    $this->isLocked = $isLocked;
    $this->groups = $groups;
    $this->attachmentFiles = $attachmentFiles;
    $this->exerciseLimits = $exerciseLimits;
    $this->exerciseConfig = $exerciseConfig;
    $this->hardwareGroups = $hardwareGroups;
    $this->exerciseEnvironmentConfigs = $exerciseEnvironmentConfigs;
    $this->exerciseTests = $exerciseTests;
    $this->pipelines = $pipelines;
    $this->referenceSolutions = new ArrayCollection();
    $this->scoreCalculator = $scoreCalculator;
    $this->scoreConfig = $scoreConfig;
    $this->configurationType = $configurationType;
  }

  public static function create(User $user, ?Group $group = NULL): Exercise {
    $groups = new ArrayCollection;
    if ($group !== null) {
      $groups->add($group);
    }

    return new self(
      1,
      "",
      new ArrayCollection,
      new ArrayCollection,
      new ArrayCollection,
      new ArrayCollection,
      new ArrayCollection,
      new ArrayCollection,
      new ArrayCollection,
      new ArrayCollection,
      new ArrayCollection,
      $groups,
      NULL,
      NULL,
      $user
    );
  }

  public static function forkFrom(Exercise $exercise, User $user, ?Group $group) {
    return new self(
      1,
      $exercise->difficulty,
      $exercise->localizedTexts,
      $exercise->runtimeEnvironments,
      $exercise->hardwareGroups,
      $exercise->supplementaryEvaluationFiles,
      $exercise->attachmentFiles,
      $exercise->exerciseLimits,
      $exercise->exerciseEnvironmentConfigs,
      $exercise->pipelines,
      $exercise->exerciseTests,
      $group ? new ArrayCollection([$group]) : new ArrayCollection,
      $exercise,
      $exercise->exerciseConfig,
      $user,
      $exercise->isPublic,
      true,
      $exercise->scoreCalculator,
      $exercise->scoreConfig
    );
  }

  public function setRuntimeEnvironments(Collection $runtimeEnvironments) {
    $this->runtimeEnvironments = $runtimeEnvironments;
  }

  public function addRuntimeEnvironment(RuntimeEnvironment $runtimeEnvironment) {
    $this->runtimeEnvironments->add($runtimeEnvironment);
  }

  public function setExerciseTests(Collection $exerciseTests) {
    $this->exerciseTests = $exerciseTests;
  }

  public function addExerciseTest(ExerciseTest $test) {
    $this->exerciseTests->add($test);
  }

  public function addHardwareGroup(HardwareGroup $hardwareGroup) {
    $this->hardwareGroups->add($hardwareGroup);
  }

  public function removeHardwareGroup(?HardwareGroup $hardwareGroup) {
    $this->hardwareGroups->removeElement($hardwareGroup);
  }

  public function addExerciseLimits(ExerciseLimits $exerciseLimits) {
    $this->exerciseLimits->add($exerciseLimits);
  }

  public function removeExerciseLimits(?ExerciseLimits $exerciseLimits) {
    $this->exerciseLimits->removeElement($exerciseLimits);
  }

  public function addExerciseEnvironmentConfig(ExerciseEnvironmentConfig $exerciseEnvironmentConfig) {
    $this->exerciseEnvironmentConfigs->add($exerciseEnvironmentConfig);
  }

  public function removeExerciseEnvironmentConfig(?ExerciseEnvironmentConfig $runtimeConfig) {
    $this->exerciseEnvironmentConfigs->removeElement($runtimeConfig);
  }

  /**
   * Get IDs of all assigned groups.
   * @return string[]
   */
  public function getGroupsIds() {
    return $this->getGroups()->map(function(Group $group) {
      return $group->getId();
    })->getValues();
  }

  public function jsonSerialize() {
    /** @var LocalizedExercise $primaryLocalization */
    $primaryLocalization = Localizations::getPrimaryLocalization($this->localizedTexts);

    return [
      "id" => $this->id,
      "name" => $primaryLocalization ? $primaryLocalization->getName() : "", # BC
      "version" => $this->version,
      "createdAt" => $this->createdAt->getTimestamp(),
      "updatedAt" => $this->updatedAt->getTimestamp(),
      "localizedTexts" => $this->localizedTexts->getValues(),
      "difficulty" => $this->difficulty,
      "runtimeEnvironments" => $this->runtimeEnvironments->getValues(),
      "hardwareGroups" => $this->hardwareGroups->getValues(),
      "forkedFrom" => $this->getForkedFrom(),
      "authorId" => $this->author->getId(),
      "groupsIds" => $this->getGroupsIds(),
      "isPublic" => $this->isPublic,
      "isLocked" => $this->isLocked,
      "description" => $primaryLocalization ? $primaryLocalization->getDescription() : "", # BC
      "supplementaryFilesIds" => $this->getSupplementaryFilesIds(),
      "attachmentFilesIds" => $this->getAttachmentFilesIds(),
      "configurationType" => $this->configurationType
    ];
  }

  public function setLocked($value = TRUE) {
    $this->isLocked = $value;
  }

  public function clearExerciseLimits() {
    $this->exerciseLimits->clear();
  }
}
