<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use DateTime;
use JsonSerializable;

#[ORM\Table]
#[ORM\Index(name: 'lock_created_at_idx', columns: ['created_at'])]
#[ORM\Entity]
class GroupExamLock implements JsonSerializable
{
    use CreatableEntity;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    protected $id;

    #[ORM\ManyToOne(targetEntity: GroupExam::class)]
    protected $groupExam;

    #[ORM\ManyToOne(targetEntity: User::class)]
    protected $student;

    /**
     * remote IP address from which the user requested locking
     */
    #[ORM\Column(type: 'string')]
    protected $remoteAddr;

    /**
     * @var DateTime|null
     * If the student is explicitly unlocked, the time is recorded.
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    protected $unlockedAt = null;

    /**
     * Constructor
     * @param GroupExam $groupExam
     * @param User $student
     * @param string $remoteAddr
     */
    public function __construct(GroupExam $groupExam, User $student, string $remoteAddr)
    {
        $this->groupExam = $groupExam;
        $this->student = $student;
        $this->remoteAddr = $remoteAddr;
        $this->createdNow();
    }

    public function jsonSerialize(): mixed
    {
        return [
            "id" => $this->getId(),
            "groupExamId" => $this->getGroupExam()->getId(),
            "studentId" => $this->getStudent()->getId(),
            "remoteAddr" => $this->getRemoteAddr(),
            "createdAt" => $this->getCreatedAt()->getTimestamp(),
            "unlockedAt" => $this->getUnlockedAt()?->getTimestamp(),
        ];
    }


    /*
     * Accessors
     */

    public function getId(): ?int
    {
        return $this->id === null ? null : (int)$this->id;
    }

    public function getGroupExam(): GroupExam
    {
        return $this->groupExam;
    }

    public function getStudent(): User
    {
        return $this->student;
    }

    public function getRemoteAddr(): string
    {
        return $this->remoteAddr;
    }

    public function getUnlockedAt(): ?DateTime
    {
        return $this->unlockedAt;
    }

    public function setUnlockedAt(?DateTime $at = new DateTime()): void
    {
        $this->unlockedAt = $at;
    }
}
