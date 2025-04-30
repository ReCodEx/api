<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(columns={"name", "exercise_id"})})
 */
class ExerciseTag
{
    use CreatableEntity;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=32)
     */
    protected $name;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     */
    protected $author;

    public function getAuthor(): ?User
    {
        return $this->author->isDeleted() ? null : $this->author;
    }

    /**
     * @ORM\ManyToOne(targetEntity="Exercise")
     */
    protected $exercise;

    public function getExercise(): ?Exercise
    {
        return $this->exercise->isDeleted() ? null : $this->exercise;
    }


    public function __construct(string $name, User $author, Exercise $exercise)
    {
        $this->name = $name;
        $this->author = $author;
        $this->exercise = $exercise;
        $this->createdNow();
    }

    /*
     * Accessors
     */

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
