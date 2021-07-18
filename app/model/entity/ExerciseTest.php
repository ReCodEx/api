<?php

namespace App\Model\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use DateTime;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class ExerciseTest implements JsonSerializable
{
    use CreateableEntity;
    use UpdateableEntity;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(type="string")
     */
    protected $name;

    /**
     * @ORM\Column(type="text")
     */
    protected $description;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     */
    protected $author;

    public function getAuthor(): ?User
    {
        return $this->author->isDeleted() ? null : $this->author;
    }

    /**
     * ExerciseTest constructor.
     * @param string $name
     * @param string $description
     * @param User|null $author
     */
    public function __construct(string $name, string $description, ?User $author)
    {
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();

        $this->name = $name;
        $this->description = $description;
        $this->author = $author;
    }

    public function jsonSerialize()
    {
        return [
            "id" => $this->id,
            "name" => $this->name,
            "description" => $this->description
        ];
    }

    ////////////////////////////////////////////////////////////////////////////

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }
}
