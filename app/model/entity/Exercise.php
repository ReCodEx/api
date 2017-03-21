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
 * @method Doctrine\Common\Collections\Collection getRuntimeConfigs()
 * @method Doctrine\Common\Collections\Collection getLocalizedTexts()
 * @method setName(string $name)
 * @method removeRuntimeConfig(RuntimeConfig $config)
 * @method removeLocalizedText(Assignment $assignment)
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
   * @ORM\ManyToMany(targetEntity="RuntimeConfig", cascade={"persist"})
   */
  protected $runtimeConfigs;

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
   * @ORM\OneToMany(targetEntity="ExerciseFile", mappedBy="exercise")
   */
  protected $supplementaryFiles;

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
   * Can a specific user access this exercise?
   */
  public function canAccessDetail(User $user) {
    if (!$user->getRole()->hasLimitedRights()) {
      return TRUE;
    }

    return $this->isPublic === TRUE || $this->isAuthor($user);
  }

  /**
   * Constructor
   */
  private function __construct($name, $version, $difficulty,
      Collection $localizedTexts, Collection $runtimeConfigs,
      Collection $supplementaryFiles, $exercise, User $user,
      $isPublic = TRUE, $description = "", $group = NULL) {
    $this->name = $name;
    $this->version = $version;
    $this->createdAt = new DateTime;
    $this->updatedAt = new DateTime;
    $this->localizedTexts = $localizedTexts;
    $this->difficulty = $difficulty;
    $this->runtimeConfigs = $runtimeConfigs;
    $this->exercise = $exercise;
    $this->author = $user;
    $this->supplementaryFiles = $supplementaryFiles;
    $this->isPublic = $isPublic;
    $this->description = $description;
    $this->group = $group;
  }

  public static function create(User $user): Exercise {
    return new self(
      "",
      1,
      "",
      new ArrayCollection,
      new ArrayCollection,
      new ArrayCollection,
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
      $exercise->runtimeConfigs,
      $exercise->supplementaryFiles,
      $exercise,
      $user,
      $exercise->isPublic,
      $exercise->description,
      $exercise->group
    );
  }

  public function addRuntimeConfig(RuntimeConfig $config) {
    $this->runtimeConfigs->add($config);
  }

  public function addLocalizedText(LocalizedText $localizedText) {
    $this->localizedTexts->add($localizedText);
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
   * Get runtime configuration based on environment identification.
   * @param RuntimeEnvironment $environment
   * @return RuntimeConfig|NULL
   */
  public function getRuntimeConfigByEnvironment(RuntimeEnvironment $environment) {
    $first = $this->runtimeConfigs->filter(
      function (RuntimeConfig $runtimeConfig) use ($environment) {
        return $runtimeConfig->getRuntimeEnvironment()->getId() === $environment->getId();
    })->first();
    return $first === FALSE ? NULL : $first;
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
      "runtimeConfigs" => $this->runtimeConfigs->getValues(),
      "forkedFrom" => $this->getForkedFrom(),
      "authorId" => $this->author->getId(),
      "groupId" => $this->group ? $this->group->getId() : NULL,
      "isPublic" => $this->isPublic,
      "description" => $this->description
    ];
  }

  public function addSupplementaryFile(UploadedFile $file) {
    $this->supplementaryFiles->add($file);
  }

}
