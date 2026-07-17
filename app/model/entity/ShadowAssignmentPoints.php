<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use DateTime;

#[ORM\Table]
#[ORM\UniqueConstraint(columns: ['shadow_assignment_id', 'awardee_id'])]
#[ORM\Entity]
class ShadowAssignmentPoints
{
    use CreatableEntity;
    use UpdatableEntity;

    public function __construct(
        int $points,
        string $note,
        ShadowAssignment $shadowAssignment,
        User $author,
        User $awardee,
        ?DateTime $awardedAt
    ) {
        $this->points = $points;
        $this->shadowAssignment = $shadowAssignment;
        $this->note = $note;
        $this->author = $author;
        $this->awardee = $awardee;
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
        $this->awardedAt = $awardedAt;
    }

    /**
     * @var \Ramsey\Uuid\UuidInterface
     */
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Ramsey\Uuid\Doctrine\UuidGenerator::class)]
    protected $id;

    #[ORM\Column(type: 'integer')]
    protected $points;

    #[ORM\Column(type: 'string', length: 1024)]
    protected $note;

    /**
     * @var ShadowAssignment
     */
    #[ORM\ManyToOne(targetEntity: ShadowAssignment::class)]
    protected $shadowAssignment;

    public function getShadowAssignment(): ?ShadowAssignment
    {
        return $this->shadowAssignment->isDeleted() ? null : $this->shadowAssignment;
    }

    /**
     * Author is the person (typically teacher) who authorized the points.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    protected $author;

    public function getAuthor(): ?User
    {
        return $this->author->isDeleted() ? null : $this->author;
    }

    /**
     * Awardee is the person (typically student) who accepted (benefit from) the points.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    protected $awardee;

    public function getAwardee(): ?User
    {
        return $this->awardee->isDeleted() ? null : $this->awardee;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    protected $awardedAt;

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function setNote(string $note): void
    {
        $this->note = $note;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): void
    {
        $this->points = $points;
    }

    public function getAwardedAt(): ?DateTime
    {
        return $this->awardedAt;
    }

    public function setAwardedAt(?DateTime $awardedAt): void
    {
        $this->awardedAt = $awardedAt;
    }
}
