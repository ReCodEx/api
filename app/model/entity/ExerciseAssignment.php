<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class ExerciseAssignment implements JsonSerializable
{
    use \Kdyby\Doctrine\Entities\MagicAccessors;

    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    public function getId() {
      return $this->id;
    }

    /**
     * @ORM\Column(type="string")
     */
    protected $name;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $firstDeadline;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $secondDeadline;

    /**
     * @ORM\Column(type="text")
     */
    protected $description;

    public function getDescription() {
      // @todo: this must be translatable

      $description = $this->description;
      $parent = $this->exercise;
      while ($description === NULL && $parent !== NULL) {
        $description = $parent->description;
        $parent = $parent->exercise;
      }

      return $description;
    }

    /**
     * @ORM\ManyToOne(targetEntity="Exercise")
     * @ORM\JoinColumn(name="exercise_id", referencedColumnName="id")
     */
    protected $exercise;

    public function getExercise() {
        return $this->exercise;
    }

    /**
     * @ORM\ManyToOne(targetEntity="Group", inversedBy="assignments")
     * @ORM\JoinColumn(name="group_id", referencedColumnName="id")
     */
    protected $group;

    public function jsonSerialize() {
      return [
        'id' => $this->id,
        'name' => $this->name,
        'description' => $this->getDescription(),
        'exercise' => $this->exercise,
        'group' => $this->group,
        'deadline' => [
          'first' => $this->firstDeadline,
          'second' => $this->secondDeadline
        ]
      ];
    }
  
    /**
     * The name of the user
     * @param  string $name   Name of the exercise
     * @return User
     */
    public static function createExerciseAssignment($name, $description, $firstDeadline, $secondDeadline, Exercise $exercise, Group $group) {
        $entity = new ExerciseAssignment;
        $entity->name = $name;
        $entity->description = $description;
        $entity->exercise = $exercise;
        $entity->group = $group;
        $entity->firstDeadline = $firstDeadline;
        $entity->secondDeadline = $secondDeadline;
        return $entity;
    }
}
