<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use DateTime;
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

    public function jsonSerialize() {
      return [
        "id" => $this->id,
        "name" => $this->name,
        "version" => $this->version,
        "authorId" => $this->author->getId(),
        "forkedFrom" => $this->getForkedFrom(),
        "description" => $this->description,
        "assignment" => $this->assignment,
        "difficulty" => $this->difficulty,
        "createdAt" => $this->createdAt,
        "updatedAt" => $this->updatedAt
      ];
    }

    /**
     * The name of the user
     * @param  string $name   Name of the exercise
     * @return Exercise
     */
    public static function createExercise($name, $description, User $author) {
      $exercise = new Exercise;
      $exercise->name = $name;
      $exercise->exercise = NULL;
      $exercise->version = 1;
      $exercise->description = $description;
      $exercise->author = $author;
      $exercise->createdAt = $exercise->updatedAt = new DateTime();
      return $exercise;
    }
}
