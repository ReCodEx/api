<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class LocalizedExercise extends LocalizedEntity implements JsonSerializable
{
    public function __construct(
        string $locale,
        string $name,
        string $assignmentText,
        string $description = "",
        ?string $externalAssignmentLink = null,
        ?LocalizedExercise $createdFrom = null
    ) {
        parent::__construct($locale);
        $this->assignmentText = $assignmentText;
        $this->name = $name;
        $this->description = $description;
        $this->createdFrom = $createdFrom;
        $this->externalAssignmentLink = $externalAssignmentLink;
    }

    /**
     * @ORM\Column(type="string")
     */
    protected $name;

    /**
     * A short description of the exercise (for teachers)
     * @ORM\Column(type="text")
     */
    protected $description;

    /**
     * Text of the assignment (for students)
     * @ORM\Column(type="text")
     */
    protected $assignmentText;

    /**
     * A link to an external assignment for students
     * @ORM\Column(type="string", length=1024, nullable=true)
     */
    protected $externalAssignmentLink;

    /**
     * @ORM\ManyToOne(targetEntity="LocalizedExercise")
     * @ORM\JoinColumn(onDelete="SET NULL")
     * @var LocalizedExercise
     */
    protected $createdFrom;

    public function equals(LocalizedEntity $other): bool
    {
        return $other instanceof LocalizedExercise
            && $this->description === $other->description
            && $this->assignmentText === $other->assignmentText
            && $this->externalAssignmentLink === $other->externalAssignmentLink
            && $this->name === $other->name;
    }

    public function setCreatedFrom(LocalizedEntity $entity)
    {
        if ($entity instanceof LocalizedExercise) {
            $this->createdFrom = $entity;
        } else {
            throw new InvalidArgumentException("Wrong type of entity supplied");
        }
    }

    /**
     * Returns true if this localization does not hold relevant assignment text nor external link
     * (i.e., it holds no useful information for the students).
     * @return bool
     */
    public function isEmpty(): bool
    {
        return (!$this->assignmentText || trim($this->assignmentText) === '')
            && (!$this->externalAssignmentLink || !filter_var($this->externalAssignmentLink, FILTER_VALIDATE_URL));
    }

    public function jsonSerialize(): mixed
    {
        return [
            "id" => $this->getId(),
            "locale" => $this->locale,
            "name" => $this->name,
            "text" => $this->assignmentText,
            "link" => $this->externalAssignmentLink ?? "",
            "description" => $this->description,
            "createdAt" => $this->createdAt->getTimestamp(),
        ];
    }

    /*
     * Accessors
     */

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getAssignmentText(): string
    {
        return $this->assignmentText;
    }
}
