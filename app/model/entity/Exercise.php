<?php

namespace App\Model\Entity;

use \DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;

/**
 * @ORM\Entity
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
   * @ORM\Column(type="datetime")
   */
  protected $createdAt;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $updatedAt;

  /**
   * @ORM\ManyToMany(targetEntity="LocalizedAssignment", inversedBy="exercises", cascade={"persist"})
   */
  protected $localizedAssignments;

  /**
   * @ORM\Column(type="string")
   */
  protected $difficulty;

  /**
   * @ORM\ManyToMany(targetEntity="SolutionRuntimeConfig")
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
   * @ORM\ManyToOne(targetEntity="User", inversedBy="exercises")
   */
  protected $author;

  public function isAuthor(User $user) {
    return $this->author->id === $user->id;
  }

  /**
   * Constructor
   */
  private function __construct($name, $version, $difficulty,
      Collection $localizedAssignments, Collection $solutionRuntimeConfigs,
      $exercise, User $user) {
    $this->name = $name;
    $this->version = $version;
    $this->createdAt = new DateTime;
    $this->updatedAt = new DateTime;
    $this->localizedAssignments = $localizedAssignments;
    $this->difficulty = $difficulty;
    $this->solutionRuntimeConfigs = $solutionRuntimeConfigs;
    $this->exercise = $exercise;
    $this->author = $user;
  }

  public static function create(User $user): Exercise {
    return new self(
      "",
      1,
      "",
      new ArrayCollection,
      new ArrayCollection,
      NULL,
      $user
    );
  }

  public static function forkFrom(Exercise $exercise, User $user): Exercise {
    return new self(
      $exercise->name,
      $exercise->version + 1,
      $exercise->difficulty,
      new ArrayCollection($exercise->getLocalizedAssignments()->getValues()),
      new ArrayCollection($exercise->getSolutionRuntimeConfigs()->getValues()),
      $exercise,
      $user
    );
  }

  public function addRuntimeConfig(SolutionRuntimeConfig $config)
  {
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
    return $this->getLocalizedAssignments()->matching($criteria)->first();
  }

  /**
   * Get runtime configuration based on environment identification.
   * @param string $environmentId
   * @return SolutionRuntimeConfig|NULL
   */
  public function getRuntimeConfigByEnvironment(string $environmentId) {
    $criteria = Criteria::create()->where(Criteria::expr()->eq("runtime_environment_id", $environmentId));
    return $this->getSolutionRuntimeConfigs()->matching($criteria)->first();
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "name" => $this->name,
      "version" => $this->version,
      "createdAt" => $this->createdAt,
      "updatedAt" => $this->updatedAt,
      "localizedAssignments" => $this->localizedAssignments->map(function($localized) { return $localized->getId(); })->getValues(),
      "difficulty" => $this->difficulty,
      "solutionRuntimeConfigs" => $this->solutionRuntimeConfigs->map(function($config) { return $config->getId(); })->getValues(),
      "forkedFrom" => $this->getForkedFrom(),
      "authorId" => $this->author->getId()
    ];
  }

}
