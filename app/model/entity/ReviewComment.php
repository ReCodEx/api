<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use DateTime;

#[ORM\Entity]
class ReviewComment implements JsonSerializable
{
    use CreatableEntity;

    /**
     * @var \Ramsey\Uuid\UuidInterface
     */
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Ramsey\Uuid\Doctrine\UuidGenerator::class)]
    protected $id;

    /**
     * @var AssignmentSolution
     */
    #[ORM\ManyToOne(targetEntity: AssignmentSolution::class, cascade: ['persist'], inversedBy: 'reviewComments')]
    protected $solution;

    /**
     * @var User
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    protected $author;

    /**
     * File represented by name, possibly with a ZIP entity reference (file.zip#entry.name)
     */
    #[ORM\Column(type: 'string')]
    protected $file;

    #[ORM\Column(type: 'integer')]
    protected $line;

    #[ORM\Column(type: 'text', length: 65535)]
    protected $text;

    /**
     * Issues are important comments, that are expected to be resolved by the student.
     */
    #[ORM\Column(type: 'boolean')]
    protected $issue = false;

    /**
     * ReviewComment constructor.
     */
    public function __construct(
        AssignmentSolution $solution,
        User $author,
        string $file,
        int $line,
        string $text,
        bool $issue = false
    ) {
        $this->solution = $solution;
        $this->author = $author;
        $this->file = $file;
        $this->line = $line;
        $this->text = $text;
        $this->issue = $issue;
        $this->createdAt = new DateTime();
    }

    public function jsonSerialize(): mixed
    {
        return [
            "id" => $this->getId(),
            "author" => $this->getAuthor() ? $this->getAuthor()->getId() : null,
            "createdAt" => $this->getCreatedAt()->getTimestamp(),
            "file" => $this->file,
            "line" => $this->line,
            "text" => $this->text,
            "issue" => $this->issue,
        ];
    }

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
    }

    public function getSolution(): AssignmentSolution
    {
        return $this->solution;
    }

    public function getAuthor(): ?User
    {
        return $this->author->isDeleted() ? null : $this->author;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }

    public function isIssue(): bool
    {
        return $this->issue;
    }

    public function setIssue(bool $issue = true): void
    {
        $this->issue = $issue;
    }
}
