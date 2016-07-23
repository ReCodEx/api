<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class Submission implements JsonSerializable
{
    use \Kdyby\Doctrine\Entities\MagicAccessors;

    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $submittedAt;

    /**
     * @ORM\Column(type="text")
     */
    protected $note;

    /**
     * @ORM\ManyToOne(targetEntity="ExerciseAssignment")
     * @ORM\JoinColumn(name="exercise_assignment_id", referencedColumnName="id")
     */
    protected $exerciseAssignment;

    public function getExerciseAssignment() {
        return $this->exerciseAssignment;
    }

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    protected $user;

    public function getUser() {
      return $this->user;
    }

    public function jsonSerialize() {
      return [
        'id' => $this->id,
        'user' => $this->getUser(),
        'note' => $this->note,
        'exerciseAssignment' => $this->getExerciseAssignment(),
        'submittedAt' => $this->submittedAt
      ];
    }
  
    /**
     * The name of the user
     * @param  string $name   Name of the exercise
     * @return User
     */
    public static function createSubmission($note, ExerciseAssignment $assignment, User $user, array $files) {
        $entity = new Exercise;
        $entity->exerciseAssignment = $assignment;
        $entity->user = $user;
        $entity->note = $note;
        $entity->submittedAt = new \DateTime;
        // $entity->files = $files;
        return $entity;
    }
}
