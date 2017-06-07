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
 * @method Collection getLocalizedTexts()
 * @method Collection getReferenceSolutions()
 * @method Collection getExerciseLimits()
 * @method setName(string $name)
 * @method removeLocalizedText(Assignment $assignment)
 * @method \DateTime getDeletedAt()
 * @method ExerciseConfig getExerciseConfig()
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
   * @ORM\ManyToMany(targetEntity="ExerciseFile", inversedBy="exercises")
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

  public function isPublic() {
    return $this->isPublic;
  }

  /**
   * @ORM\Column(type="text")
   */
  protected $description;

  /**
   * @ORM\ManyToOne(targetEntity="Group", inversedBy="exercises")
   */
  protected $group;

  /**
   * @ORM\ManyToMany(targetEntity="ExerciseLimits", inversedBy="exercises", cascade={"persist"})
   */
  protected $exerciseLimits;

  /**
   * @ORM\ManyToOne(targetEntity="ExerciseConfig", inversedBy="exercises", cascade={"persist"})
   */
  protected $exerciseConfig;

  /**
   * Can a specific user access this exercise?
   * @param User $user
   * @return boolean
   */
  public function canAccessDetail(User $user): bool {
    if (!$user->getRole()->hasLimitedRights() || $this->isAuthor($user)) {
      return TRUE;
    }

    if ($this->group) {
      return $this->isPublic() && ($this->group->isAdminOf($user)
          || $this->group->isSupervisorOf($user));
    } else {
      return $this->isPublic();
    }
  }

  /**
   * Can a specific user modify this exercise?
   * @param \App\Model\Entity\User $user
   * @return boolean
   */
  public function canModifyDetail(User $user): bool {
    return $this->isAuthor($user) || !$user->getRole()->hasLimitedRights();
  }

  /**
   * Constructor
   * @param string $name
   * @param $version
   * @param $difficulty
   * @param Collection $localizedTexts
   * @param Collection $runtimeEnvironments
   * @param Collection $supplementaryEvaluationFiles
   * @param Collection $additionalFiles
   * @param Collection $exerciseLimits
   * @param Exercise|null $exercise
   * @param User $user
   * @param Group|null $group
   * @param bool $isPublic
   * @param string $description
   * @param ExerciseConfig|null $exerciseConfig
   */
  private function __construct(string $name, $version, $difficulty,
      Collection $localizedTexts, Collection $runtimeEnvironments,
      Collection $supplementaryEvaluationFiles, Collection $additionalFiles,
      Collection $exerciseLimits, ?Exercise $exercise, User $user,
      ?Group $group = NULL, bool $isPublic = TRUE, string $description = "",
      ?ExerciseConfig $exerciseConfig = NULL) {
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
    $this->description = $description;
    $this->group = $group;
    $this->additionalFiles = $additionalFiles;
    $this->exerciseLimits = $exerciseLimits;
    $this->exerciseConfig = $exerciseConfig;
  }

  public static function create(User $user, ?Group $group = NULL): Exercise {
    return new self(
      "",
      1,
      "",
      new ArrayCollection,
      new ArrayCollection,
      new ArrayCollection,
      new ArrayCollection,
      new ArrayCollection,
      NULL,
      $user,
      $group
    );
  }

  public static function forkFrom(Exercise $exercise, User $user) {
    return new self(
      $exercise->name,
      1,
      $exercise->difficulty,
      $exercise->localizedTexts,
      $exercise->runtimeEnvironments,
      $exercise->supplementaryEvaluationFiles,
      $exercise->additionalFiles,
      $exercise->exerciseLimits,
      $exercise,
      $user,
      $exercise->group,
      $exercise->isPublic,
      $exercise->description,
      $exercise->exerciseConfig
    );
  }

  public function addRuntimeEnvironment(RuntimeEnvironment $runtimeEnvironment) {
    $this->runtimeEnvironments->add($runtimeEnvironment);
  }

  public function addLocalizedText(LocalizedText $localizedText) {
    $this->localizedTexts->add($localizedText);
  }

  public function addSupplementaryEvaluationFile(ExerciseFile $exerciseFile) {
    $this->supplementaryEvaluationFiles->add($exerciseFile);
  }

  public function addAdditionalFile(AdditionalExerciseFile $exerciseFile) {
    $this->additionalFiles->add($exerciseFile);
  }

  public function addExerciseLimits(ExerciseLimits $exerciseLimits) {
    $this->exerciseLimits->add($exerciseLimits);
  }

  public function removeExerciseLimits(ExerciseLimits $exerciseLimits) {
    $this->exerciseLimits->removeElement($exerciseLimits);
  }

  /**
   * Get localized text based on given locale.
   * @param string $locale
   * @return LocalizedText|NULL
   */
  public function getLocalizedTextByLocale(string $locale) {
    $criteria = Criteria::create()->where(Criteria::expr()->eq("locale", $locale));
    $first = $this->localizedTexts->matching($criteria)->first();
    return $first === FALSE ? NULL : $first;
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
   * @return ArrayCollection
   */
  public function getRuntimeEnvironmentsIds() {
    return $this->runtimeEnvironments->map(function($config) { return $config->getId(); })->getValues();
  }

  public function getSupplementaryFilesIds() {
    return $this->supplementaryEvaluationFiles->map(
      function(ExerciseFile $file) {
        return $file->getId();
      })->getValues();
  }

  public function getAdditionalExerciseFilesIds() {
    return $this->additionalFiles->map(
      function(AdditionalExerciseFile $file) {
        return $file->getId();
      })->getValues();
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "name" => $this->name,
      "version" => $this->version,
      "createdAt" => $this->createdAt->getTimestamp(),
      "updatedAt" => $this->updatedAt->getTimestamp(),
      "localizedTexts" => $this->localizedTexts->getValues(),
      "difficulty" => $this->difficulty,
      "runtimeEnvironments" => $this->runtimeEnvironments->getValues(),
      "forkedFrom" => $this->getForkedFrom(),
      "authorId" => $this->author->getId(),
      "groupId" => $this->group ? $this->group->getId() : NULL,
      "isPublic" => $this->isPublic,
      "description" => $this->description,
      "supplementaryFilesIds" => $this->getSupplementaryFilesIds(),
      "additionalExerciseFilesIds" => $this->getAdditionalExerciseFilesIds()
    ];
  }

}
