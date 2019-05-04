<?php

namespace App\Model\Entity;

use App\Helpers\Evaluation\IExercise;
use \DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine;
use Exception;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 *
 * @method string getId()
 * @method Collection getExerciseLimits()
 * @method Collection getExerciseEnvironmentConfigs()
 * @method DateTime getCreatedAt()
 * @method string getDifficulty()
 * @method Collection getReferenceSolutions()
 * @method Collection getExerciseTests()
 * @method Collection getTags()
 * @method void setScoreConfig(string $scoreConfig)
 * @method void setDifficulty(string $difficulty)
 * @method void setIsPublic(bool $isPublic)
 * @method void setExerciseConfig(ExerciseConfig $exerciseConfig)
 * @method setConfigurationType($type)
 * @method void addGroup(Group $group)
 * @method void addTag(ExerciseTag $tag)
 * @method void removeTag(ExerciseTag $tag)
 * @method void removeGroup(Group $group)
 */
class Exercise implements IExercise
{
  use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;
  use ExerciseData;
  use UpdateableEntity;
  use DeleteableEntity;
  use VersionableEntity;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

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

  public function getForkedFrom(): ?Exercise {
      return $this->exercise && $this->exercise->isDeleted() ? null : $this->exercise;
  }

  /**
   * @ORM\OneToMany(targetEntity="ReferenceExerciseSolution", mappedBy="exercise")
   */
  protected $referenceSolutions;

  /**
   * @ORM\ManyToOne(targetEntity="User", inversedBy="exercises")
   */
  protected $author;

  public function isAuthor(User $user) {
    return $this->author && $this->author->getId() === $user->getId();
  }

  public function getAuthor() {
    return $this->author->isDeleted() ? null : $this->author;
  }

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isPublic;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isLocked;

  /**
   * @ORM\Column(type="boolean", options={"default":0})
   */
  protected $isBroken = false;

  public function isPublic() {
    return $this->isPublic;
  }

  public function isLocked() {
    return $this->isLocked;
  }

  public function isBroken() {
    return $this->isBroken;
  }

  public function setBroken(string $message) {
    $this->isBroken = true;
    $this->validationError = $message;
  }

  public function setNotBroken() {
    $this->isBroken = false;
  }

  /**
   * @ORM\Column(type="text")
   */
  protected $validationError;

  public function getValidationError(): ?string {
    if ($this->isBroken) {
      return $this->validationError;
    }

    return null;
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
      return !$group->isDeleted();
    });
  }

  /**
   * @ORM\OneToMany(targetEntity="Assignment", mappedBy="exercise")
   */
  protected $assignments;

  /**
   * @return Collection
   */
  public function getAssignments() {
    return $this->assignments->filter(function (Assignment $assignment) {
      return !$assignment->isDeleted();
    });
  }

  /**
   * @ORM\ManyToMany(targetEntity="Pipeline", inversedBy="exercises")
   */
  protected $pipelines;

  public function getPipelines() {
    return $this->pipelines->filter(function (Pipeline $pipeline) {
      return !$pipeline->isDeleted();
    });
  }

  public function addPipeline(Pipeline $pipeline) {
    $this->pipelines->add($pipeline);
    $pipeline->getAllExercises()->add($this);
  }

  public function removePipeline(Pipeline $pipeline) {
    $this->pipelines->removeElement($pipeline);
    $pipeline->getAllExercises()->removeElement($this);
  }

  /**
   * @var Collection
   * @ORM\OneToMany(targetEntity="ExerciseTag", mappedBy="exercise", cascade={"persist", "remove"})
   */
  protected $tags;

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
   * @throws Exception
   */
  private function __construct($version, $difficulty,
      Collection $localizedTexts, Collection $runtimeEnvironments,
      Collection $hardwareGroups, Collection $supplementaryEvaluationFiles,
      Collection $attachmentFiles, Collection $exerciseLimits,
      Collection $exerciseEnvironmentConfigs, Collection $pipelines,
      Collection $exerciseTests, Collection $groups, ?Exercise $exercise,
      ?ExerciseConfig $exerciseConfig, User $user, bool $isPublic = false,
      bool $isLocked = true, string $scoreCalculator = null,
      string $scoreConfig = "", string $configurationType = "simpleExerciseConfig") {
    $this->version = $version;
    $this->createdAt = new DateTime();
    $this->updatedAt = new DateTime();
    $this->localizedTexts = $localizedTexts;
    $this->difficulty = $difficulty;
    $this->runtimeEnvironments = $runtimeEnvironments;
    $this->exercise = $exercise;
    $this->author = $user;
    $this->supplementaryEvaluationFiles = $supplementaryEvaluationFiles;
    $this->isPublic = $isPublic;
    $this->isLocked = $isLocked;
    $this->isBroken = false;
    $this->groups = $groups;
    $this->assignments = new ArrayCollection();
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
    $this->validationError = "";
    $this->tags = new ArrayCollection();
  }

  public static function create(User $user, Group $group): Exercise {
    return new self(
      1,
      "",
      new ArrayCollection(),
      new ArrayCollection(),
      new ArrayCollection(),
      new ArrayCollection(),
      new ArrayCollection(),
      new ArrayCollection(),
      new ArrayCollection(),
      new ArrayCollection(),
      new ArrayCollection(),
      new ArrayCollection([$group]),
      null,
      null,
      $user
    );
  }

  public static function forkFrom(Exercise $exercise, User $user, Group $group) {
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
      new ArrayCollection([$group]),
      $exercise,
      $exercise->exerciseConfig,
      $user,
      $exercise->isPublic,
      true,
      $exercise->scoreCalculator,
      $exercise->scoreConfig,
      $exercise->configurationType
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

  public function setLocked($value = true) {
    $this->isLocked = $value;
  }

  public function clearExerciseLimits() {
    $this->exerciseLimits->clear();
  }
}
