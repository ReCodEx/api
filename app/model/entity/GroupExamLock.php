<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use DateTime;
use JsonSerializable;

/**
 * @ORM\Entity
 * Logs locking events for a particular exam. Every time student acquires group-lock, this entity is created.
 * If the user is explicitly unlocked, the time of that event is also recorded.
 */
class GroupExamLock implements JsonSerializable
{
    use CreateableEntity;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="GroupExam")
     */
    protected $groupExam;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     */
    protected $student;

    /**
     * @ORM\Column(type="string")
     * remote IP address from which the user requested locking
     */
    protected $ip;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var DateTime|null
     * If the student is explicitly unlocked, the time is recorded.
     */
    protected $unlockedAt = null;

    /**
     * Constructor
     * @param GroupExam $groupExam
     * @param User $student
     * @param string $ip
     */
    public function __construct(GroupExam $groupExam, User $student, string $ip)
    {
        $this->groupExam = $groupExam;
        $this->student = $student;
        $this->ip = $ip;
        $this->createdNow();
    }

    public function jsonSerialize(): mixed
    {
        return [
            "id" => $this->getId(),
            "groupExamId" => $this->getGroupExam()->getId(),
            "studentId" => $this->getStudent()->getId(),
            "ip" => $this->getIp(),
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

    public function getIp(): string
    {
        return $this->ip;
    }

    public function getUnlockedAt(): ?DateTime
    {
        return $this->unlockedAt;
    }
}
