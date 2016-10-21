<?php

namespace App\Model\Entity;

use \DateTime;
use Doctrine\ORM\Mapping as ORM;
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
   * @ORM\Column(type="text")
   */
  protected $description;

  /**
   * @ORM\Column(type="text")
   */
  protected $assignment;

  /**
   * @ORM\Column(type="string")
   */
  protected $difficulty;

  /**
   * @ORM\Column(type="string")
   */
  protected $jobConfigFilePath;

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
  private function __construct($name, $version, $description, $assignment,
      $difficulty, $jobConfigFilePath, $exercise, User $user) {
    $this->name = $name;
    $this->version = $version;
    $this->createdAt = new DateTime;
    $this->updatedAt = new DateTime;
    $this->description = $description;
    $this->assignment = $assignment;
    $this->difficulty = $difficulty;
    $this->jobConfigFilePath = $jobConfigFilePath;
    $this->exercise = $exercise;
    $this->author = $user;
  }

  public function update($name, $description, $assignment, $difficulty) {
    $this->name = $name;
    $this->version++;
    $this->updatedAt = new DateTime;
    $this->description = $description;
    $this->assignment = $assignment;
    $this->difficulty = $difficulty;
  }

  public static function create(User $user): Exercise {
    return new self(
      "",
      1,
      "",
      "",
      "",
      "",
      NULL,
      $user
    );
  }

  public static function forkFrom(Exercise $exercise, User $user): Exercise {
    return new self(
      $exercise->name,
      $exercise->version + 1,
      $exercise->description,
      $exercise->assignment,
      $exercise->difficulty,
      $exercise->jobConfigFilePath,
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
      "assignment" => $this->assignment,
      "difficulty" => $this->difficulty,
      "jobConfigFilePath" => $this->jobConfigFilePath,
      "forkedFrom" => $this->getForkedFrom(),
      "authorId" => $this->author->getId()
    ];
  }

}
