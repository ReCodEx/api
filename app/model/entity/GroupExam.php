<?php

namespace App\Model\Entity;

use App\Model\GroupExamLockType;
use Doctrine\ORM\Mapping as ORM;
use DateTime;
use JsonSerializable;

/**
 * @ORM\Entity
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(columns={"group_id", "begin"})})
 * Holds history record of an exam that took place in a group.
 * The `examBegin`, `examEnd` fields are copied from group to `begin`, `end` fields here,
 * `examLockType` is copied to `lockType` field.
 * This entity is created when the first user locks in (i.e., only exams with users are recorded in history).
 */
class GroupExam implements JsonSerializable
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     * @var int|null
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="Group", inversedBy="exams")
     * @var Group
     */
    protected $group;

    /**
     * @ORM\Column(type="datetime")
     * @var DateTime|null
     */
    protected $begin = null;

    /**
     * @ORM\Column(type="datetime")
     * @var DateTime|null
     */
    protected $end = null;

    /**
     * @ORM\Column(type="string")
     * Saved value from examLockType flag.
     * @var string
     */
    protected $lockType = GroupExamLockType::Visible->value;

    /**
     * Constructor
     * @param Group $group
     * @param DateTime $begin
     * @param DateTime $end
     * @param GroupExamLockType $type
     */
    public function __construct(Group $group, DateTime $begin, DateTime $end, GroupExamLockType $type)
    {
        $this->group = $group;
        $this->begin = $begin;
        $this->end = $end;
        $this->lockType = $type->value;
    }

    /**
     * Update the parameters (happens if pending exam is cut short, for instance).
     * @param DateTime $begin
     * @param DateTime $end
     * @param GroupExamLockType $type
     */
    public function update(DateTime $begin, DateTime $end, GroupExamLockType $type): void
    {
        $this->begin = $begin;
        $this->end = $end;
        $this->lockType = $type->value;
    }

    public function jsonSerialize(): mixed
    {
        $group = $this->getGroup();
        return [
            "id" => $this->getId(),
            "groupId" => $group ? $group->getId() : null,
            "begin" => $this->getBegin()->getTimestamp(),
            "end" => $this->getEnd()->getTimestamp(),
            "type" => $this->lockType,
            // BC only, DEPRECATED
            "strict" => $this->lockType === GroupExamLockType::Restricted->value,
        ];
    }


    /*
     * Accessors
     */

    public function getId(): ?int
    {
        return $this->id === null ? null : (int)$this->id;
    }

    public function getGroup(): ?Group
    {
        return $this->group->isDeleted() ? null : $this->group;
    }

    public function getBegin(): DateTime
    {
        return $this->begin;
    }

    public function getEnd(): DateTime
    {
        return $this->end;
    }

    public function isLockStrict(): bool
    {
        return $this->lockType === GroupExamLockType::Restricted->value;
    }

    public function getLockType(): GroupExamLockType
    {
        return GroupExamLockType::from($this->lockType);
    }
}
