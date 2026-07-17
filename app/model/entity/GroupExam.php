<?php

namespace App\Model\Entity;

use App\Model\GroupExamLockType;
use Doctrine\ORM\Mapping as ORM;
use DateTime;
use JsonSerializable;

#[ORM\Table]
#[ORM\UniqueConstraint(columns: ['group_id', 'begin'])]
#[ORM\Entity]
class GroupExam implements JsonSerializable
{
    /**
     * @var int|null
     */
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    protected $id;

    /**
     * @var Group
     */
    #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: 'exams')]
    protected $group;

    /**
     * @var DateTime|null
     */
    #[ORM\Column(type: 'datetime')]
    protected $begin = null;

    /**
     * @var DateTime|null
     */
    #[ORM\Column(type: 'datetime')]
    protected $end = null;

    /**
     * Saved value from examLockType flag.
     * @var string
     */
    #[ORM\Column(type: 'string')]
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

    public function getLockType(): GroupExamLockType
    {
        return GroupExamLockType::from($this->lockType);
    }
}
