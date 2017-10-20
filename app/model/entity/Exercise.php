<?php

namespace App\Model\Entity;

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
 * @method string getId()
 * @method string getName()
 * @method Collection getRuntimeEnvironments()
 * @method Collection getHardwareGroups()
 * @method Collection getLocalizedTexts()
 * @method Collection getExerciseLimits()
 * @method Collection getExerciseEnvironmentConfigs()
 * @method Collection getSupplementaryEvaluationFiles()
 * @method setName(string $name)
 * @method removeLocalizedText(LocalizedText $assignment)
 * @method \DateTime getDeletedAt()
 * @method ExerciseConfig getExerciseConfig()
 * @method User getAuthor()
 * @method Doctrine\Common\Collections\Collection getAdditionalFiles()
 * @method int getVersion()
 * @method void setDifficulty(string $difficulty)
 * @method void setIsPublic(bool $isPublic)
 * @method void setUpdatedAt(DateTime $date)
 * @method void setDescription(string $description)
 * @method void setExerciseConfig(ExerciseConfig $exerciseConfig)
 */
class Exercise implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="string")
   */
  protected $name;

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
   * @ORM\Column(type="datetime")
   */
  protected $updatedAt;

  /**
   * @ORM\Column(type="datetime", nullable=true)
   */
  protected $deletedAt;

  /**
   * @ORM\ManyToMany(targetEntity="LocalizedText", inversedBy="exercises")
   * @var Collection|Selectable
   */
  protected $localizedTexts;

  /**
   * @ORM\Column(type="string")
   */
  protected $difficulty;

  /**
   * @ORM\ManyToMany(targetEntity="RuntimeEnvironment")
   */
  protected $runtimeEnvironments;

  /**
   * @ORM\ManyToMany(targetEntity="HardwareGroup")
   */
  protected $hardwareGroups;

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
   * @ORM\ManyToMany(targetEntity="SupplementaryExerciseFile", inversedBy="exercises")
   */
  protected $supplementaryEvaluationFiles;

  /**
   * @ORM\ManyToMany(targetEntity="AdditionalExerciseFile", inversedBy="exercises")
   */
  protected $additionalFiles;

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
   * @ORM\Column(type="text")
   */
  protected $description;

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
   * @ORM\ManyToMany(targetEntity="ExerciseLimits", inversedBy="exercises", cascade={"persist"})
   */
  protected $exerciseLimits;

  /**
   * @ORM\ManyToMany(targetEntity="ExerciseEnvironmentConfig", inversedBy="exercises", cascade={"persist"})
   * @var Collection|Selectable
   */
  protected $exerciseEnvironmentConfigs;

  /**
   * @ORM\ManyToOne(targetEntity="ExerciseConfig", inversedBy="exercises", cascade={"persist"})
   */
  protected $exerciseConfig;

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
   * @param string $name
   * @param $version
   * @param $difficulty
   * @param Collection $localizedTexts
   * @param Collection $runtimeEnvironments
   * @param Collection $hardwareGroups
   * @param Collection $supplementaryEvaluationFiles
   * @param Collection $additionalFiles
   * @param Collection $exerciseLimits
   * @param Collection $exerciseEnvironmentConfigs
   * @param Collection $pipelines
   * @param Collection $groups
   * @param Exercise|null $exercise
   * @param ExerciseConfig|null $exerciseConfig
   * @param User $user
   * @param bool $isPublic
   * @param string $description
   * @param bool $isLocked
   */
  private function __construct(string $name, $version, $difficulty,
      Collection $localizedTexts, Collection $runtimeEnvironments,
      Collection $hardwareGroups, Collection $supplementaryEvaluationFiles,
      Collection $additionalFiles, Collection $exerciseLimits,
      Collection $exerciseEnvironmentConfigs, Collection $pipelines,
      Collection $groups = NULL, ?Exercise $exercise,
      ?ExerciseConfig $exerciseConfig = NULL, User $user, bool $isPublic = TRUE,
      string $description = "", bool $isLocked = FALSE) {
    $this->name = $name;
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
    $this->description = $description;
    $this->groups = $groups;
    $this->additionalFiles = $additionalFiles;
    $this->exerciseLimits = $exerciseLimits;
    $this->exerciseConfig = $exerciseConfig;
    $this->hardwareGroups = $hardwareGroups;
    $this->exerciseEnvironmentConfigs = $exerciseEnvironmentConfigs;
    $this->pipelines = $pipelines;
    $this->referenceSolutions = new ArrayCollection();
  }

  public static function create(User $user, ?Group $group = NULL): Exercise {
    $groups = new ArrayCollection;
    if ($group !== null) {
      $groups->add($group);
    }

    return new self(
      "",
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
      $groups,
      NULL,
      NULL,
      $user
    );
  }

  public static function forkFrom(Exercise $exercise, User $user) {
    return new self(
      $exercise->name,
      1,
      $exercise->difficulty,
      $exercise->localizedTexts,
      $exercise->runtimeEnvironments,
      $exercise->hardwareGroups,
      $exercise->supplementaryEvaluationFiles,
      $exercise->additionalFiles,
      $exercise->exerciseLimits,
      $exercise->exerciseEnvironmentConfigs,
      $exercise->pipelines,
      $exercise->groups,
      $exercise,
      $exercise->exerciseConfig,
      $user,
      $exercise->isPublic,
      $exercise->description
    );
  }

  public function setRuntimeEnvironments(Collection $runtimeEnvironments) {
    $this->runtimeEnvironments = $runtimeEnvironments;
  }

  public function addRuntimeEnvironment(RuntimeEnvironment $runtimeEnvironment) {
    $this->runtimeEnvironments->add($runtimeEnvironment);
  }

  public function addHardwareGroup(HardwareGroup $hardwareGroup) {
    $this->hardwareGroups->add($hardwareGroup);
  }

  public function removeHardwareGroup(?HardwareGroup $hardwareGroup) {
    $this->hardwareGroups->removeElement($hardwareGroup);
  }

  public function addLocalizedText(LocalizedText $localizedText) {
    $this->localizedTexts->add($localizedText);
  }

  public function addSupplementaryEvaluationFile(SupplementaryExerciseFile $exerciseFile) {
    $this->supplementaryEvaluationFiles->add($exerciseFile);
  }

  public function addAdditionalFile(AdditionalExerciseFile $exerciseFile) {
    $this->additionalFiles->add($exerciseFile);
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
   * @param SupplementaryExerciseFile $file
   * @return bool
   */
  public function removeSupplementaryEvaluationFile(SupplementaryExerciseFile $file) {
    return $this->supplementaryEvaluationFiles->removeElement($file);
  }

  /**
   * @param AdditionalExerciseFile $file
   * @return bool
   */
  public function removeAdditionalFile(AdditionalExerciseFile $file) {
    return $this->additionalFiles->removeElement($file);
  }

  /**
   * Get localized text based on given locale.
   * @param string $locale
   * @return LocalizedText|NULL
   */
  public function getLocalizedTextByLocale(string $locale) {
    $criteria = Criteria::create()->where(Criteria::expr()->eq("locale", $locale));
    $first = $this->localizedTexts->matching($criteria)->first();
    return $first === false ? null : $first;
  }

  /**
   * Get runtime configuration based on environment identification.
   * @param RuntimeEnvironment $environment
   * @return ExerciseEnvironmentConfig|NULL
   */
  public function getExerciseEnvironmentConfigByEnvironment(RuntimeEnvironment $environment) {
    $first = $this->exerciseEnvironmentConfigs->filter(
      function (ExerciseEnvironmentConfig $runtimeConfig) use ($environment) {
        return $runtimeConfig->getRuntimeEnvironment()->getId() === $environment->getId();
      })->first();
    return $first === false ? null : $first;
  }

  /**
   * Get exercise limits based on environment.
   * @param RuntimeEnvironment $environment
   * @return ExerciseLimits[]
   */
  public function getLimitsByEnvironment(RuntimeEnvironment $environment): array {
    $result = $this->exerciseLimits->filter(
      function (ExerciseLimits $exerciseLimits) use ($environment) {
        return $exerciseLimits->getRuntimeEnvironment()->getId() === $environment->getId();
      });
    return $result->getValues();
  }

  /**
   * Get exercise limits based on environment and hardware group.
   * @param RuntimeEnvironment $environment
   * @param HardwareGroup $hwGroup
   * @return ExerciseLimits|NULL
   */
  public function getLimitsByEnvironmentAndHwGroup(RuntimeEnvironment $environment, HardwareGroup $hwGroup): ?ExerciseLimits {
    $first = $this->exerciseLimits->filter(
      function (ExerciseLimits $exerciseLimits) use ($environment, $hwGroup) {
        return $exerciseLimits->getRuntimeEnvironment()->getId() === $environment->getId()
          && $exerciseLimits->getHardwareGroup()->getId() === $hwGroup->getId();
      })->first();
    return $first === FALSE ? NULL : $first;
  }

  /**
   * Get IDs of all available runtime environments
   * @return array
   */
  public function getRuntimeEnvironmentsIds() {
    return $this->runtimeEnvironments->map(function($config) { return $config->getId(); })->getValues();
  }

  /**
   * Get IDs of all defined hardware groups.
   * @return string[]
   */
  public function getHardwareGroupsIds() {
    return $this->hardwareGroups->map(function($group) { return $group->getId(); })->getValues();
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

  /**
   * Get identifications of supplementary evaluation files.
   * @return array
   */
  public function getSupplementaryFilesIds() {
    return $this->supplementaryEvaluationFiles->map(
      function(SupplementaryExerciseFile $file) {
        return $file->getId();
      })->getValues();
  }

  /**
   * Get identifications of additional exercise files.
   * @return array
   */
  public function getAdditionalExerciseFilesIds() {
    return $this->additionalFiles->map(
      function(AdditionalExerciseFile $file) {
        return $file->getId();
      })->getValues();
  }

  protected function getSerializedLocalizedTexts() {
    return array_map(function (LocalizedText $text) {
      $data = $text->jsonSerialize();
      if ($data["shortText"] === NULL) {
        $data["shortText"] = $this->name;
      }
      return $data;
    }, $this->localizedTexts->getValues());
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "name" => $this->name,
      "version" => $this->version,
      "createdAt" => $this->createdAt->getTimestamp(),
      "updatedAt" => $this->updatedAt->getTimestamp(),
      "localizedTexts" => $this->getSerializedLocalizedTexts(),
      "difficulty" => $this->difficulty,
      "runtimeEnvironments" => $this->runtimeEnvironments->getValues(),
      "hardwareGroups" => $this->hardwareGroups->getValues(),
      "forkedFrom" => $this->getForkedFrom(),
      "authorId" => $this->author->getId(),
      "groupsIds" => $this->getGroupsIds(),
      "isPublic" => $this->isPublic,
      "isLocked" => $this->isLocked,
      "description" => $this->description,
      "supplementaryFilesIds" => $this->getSupplementaryFilesIds(),
      "additionalExerciseFilesIds" => $this->getAdditionalExerciseFilesIds()
    ];
  }

  public function setLocked($value = TRUE) {
    $this->isLocked = $value;
  }

  public function clearExerciseLimits() {
    $this->exerciseLimits->clear();
  }
}
