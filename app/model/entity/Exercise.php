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

/**
 * @ORM\Entity
 * @method string getId()
 * @method string getName()
 * @method Doctrine\Common\Collections\Collection getSolutionRuntimeConfigs()
 * @method Doctrine\Common\Collections\Collection getLocalizedAssignments()
 * @method setName(string $name)
 * @method addSolutionRuntimeConfig(SolutionRuntimeConfig $config)
 * @method removeSolutionRuntimeConfig(SolutionRuntimeConfig $config)
 * @method removeLocalizedAssignment(Assignment $assignment)
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
   * @ORM\ManyToMany(targetEntity="LocalizedAssignment", inversedBy="exercises")
   * @var Collection|Selectable
   */
  protected $localizedAssignments;

  /**
   * @ORM\Column(type="string")
   */
  protected $difficulty;

  /**
   * @ORM\ManyToMany(targetEntity="SolutionRuntimeConfig", cascade={"persist"})
   */
  protected $solutionRuntimeConfigs;

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
      Collection $localizedAssignments, Collection $solutionRuntimeConfigs,
      Collection $supplementaryFiles,
      $exercise, User $user, $isPublic = TRUE, $description = "") {
    $this->name = $name;
    $this->version = $version;
    $this->createdAt = new DateTime;
    $this->updatedAt = new DateTime;
    $this->localizedAssignments = $localizedAssignments;
    $this->difficulty = $difficulty;
    $this->solutionRuntimeConfigs = $solutionRuntimeConfigs;
    $this->exercise = $exercise;
    $this->author = $user;
    $this->supplementaryFiles = $supplementaryFiles;
    $this->isPublic = $isPublic;
    $this->description = $description;
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
      $exercise->localizedAssignments,
      $exercise->solutionRuntimeConfigs,
      $exercise->supplementaryFiles,
      $exercise,
      $user,
      $exercise->isPublic,
      $exercise->description
    );
  }

  public function addRuntimeConfig(SolutionRuntimeConfig $config) {
    $this->solutionRuntimeConfigs->add($config);
  }

  public function addLocalizedAssignment(LocalizedAssignment $localizedAssignment) {
    $this->localizedAssignments->add($localizedAssignment);
  }

  /**
   * Get localized assignment based on given locale.
   * @param string $locale
   * @return LocalizedAssignment|NULL
   */
  public function getLocalizedAssignmentByLocale(string $locale) {
    $criteria = Criteria::create()->where(Criteria::expr()->eq("locale", $locale));
    $first = $this->localizedAssignments->matching($criteria)->first();
    return $first === FALSE ? NULL : $first;
  }

  /**
   * Get runtime configuration based on environment identification.
   * @param RuntimeEnvironment $environment
   * @return SolutionRuntimeConfig|NULL
   */
  public function getRuntimeConfigByEnvironment(RuntimeEnvironment $environment) {
    $first = $this->solutionRuntimeConfigs->filter(
      function (SolutionRuntimeConfig $runtimeConfig) use ($environment) {
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
      "localizedAssignments" => $this->localizedAssignments->getValues(),
      "difficulty" => $this->difficulty,
      "solutionRuntimeConfigs" => $this->solutionRuntimeConfigs->getValues(),
      "forkedFrom" => $this->getForkedFrom(),
      "authorId" => $this->author->getId(),
      "isPublic" => $this->isPublic,
      "description" => $this->description
    ];
  }

  public function addSupplementaryFile(UploadedFile $file) {
    $this->supplementaryFiles->add($file);
  }

}
