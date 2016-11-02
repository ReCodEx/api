<?php

namespace App\Model\Entity;

use \DateTime;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

// TODO: jobConfigFilePath lost

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
   * @ORM\Column(type="text")
   */
  protected $description;

  /**
   * @ORM\ManyToMany(targetEntity="LocalizedAssignment")
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
   * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
   */
  protected $author;

  public function isAuthor(User $user) {
    return $this->author->id === $user->id;
  }

  /**
   * Constructor
   */
  private function __construct($name, $version, $description,
      $difficulty, $solutionRuntimeConfigs, $exercise, User $user) {
    $this->name = $name;
    $this->version = $version;
    $this->createdAt = new DateTime;
    $this->updatedAt = new DateTime;
    $this->localizedAssignments = new ArrayCollection;
    $this->description = $description;
    $this->difficulty = $difficulty;
    $this->solutionRuntimeConfigs = $solutionRuntimeConfigs;
    $this->exercise = $exercise;
    $this->author = $user;
  }

  public function update($name, $description, $difficulty) {
    $this->name = $name;
    $this->version++;
    $this->updatedAt = new DateTime;
    $this->description = $description;
    $this->difficulty = $difficulty;
  }

  // @todo: Update localized assignment

  public static function create(User $user): Exercise {
    return new self(
      "",
      1,
      "",
      "",
      new ArrayCollection,
      NULL,
      $user
    );
  }

  public static function forkFrom(Exercise $exercise, User $user): Exercise {
    return new self(
      $exercise->name,
      $exercise->version + 1,
      $exercise->description,
      $exercise->difficulty,
      $exercise->getSolutionRuntimeConfigs(),
      $exercise,
      $user
    );
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "name" => $this->name,
      "version" => $this->version,
      "createdAt" => $this->createdAt,
      "updatedAt" => $this->updatedAt,
      "description" => $this->description,
      "localizedAssignments" => $this->localizedAssignments->map(function($localized) { return $localized->getId(); })->getValues(),
      "difficulty" => $this->difficulty,
      "solutionRuntimeConfigs" => $this->solutionRuntimeConfigs->map(function($config) { return $config->getId(); })->getValues(),
      "forkedFrom" => $this->getForkedFrom(),
      "authorId" => $this->author->getId()
    ];
  }

    /**
     * The name of the user
     * @param  string $name   Name of the exercise
     * @return User
     */
    public static function createExercise($name, $description, User $user) {
        $entity = new Exercise;
        $entity->name = $name;
        $entity->exercise = NULL;
        $entity->version = 1;
        $entity->description = $description;
        $entity->user = $user;
        return $entity;
    }
}
