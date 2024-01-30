<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use DateTime;
use JsonSerializable;

/**
 * @ORM\Entity
 * @ORM\Table(indexes={@ORM\Index(name="group_begin_idx", columns={"group_id", "begin"})})
 * Holds history record of an exam that took place in a group.
 * The `examBegin`, `examEnd` fields are copied from group to `begin`, `end` fields here.
 * This entity is created when the first user locks in (i.e., only exams with users are recorded in history).
 */
class GroupExam implements JsonSerializable
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="Group", inversedBy="exams")
     */
    protected $group;

    /**
     * @ORM\Column(type="datetime")
     * @var DateTime
     */
    protected $begin = null;

    /**
     * @ORM\Column(type="datetime")
     * @var DateTime
     */
    protected $end = null;

    /**
     * Constructor
     * @param Group $group
     * @param DateTime $begin
     * @param DateTime $end
     */
    public function __construct(Group $group, DateTime $begin, DateTime $end)
    {
        $this->group = $group;
        $this->begin = $begin;
        $this->end = $end;
    }

    public function jsonSerialize(): mixed
    {
        $group = $this->getGroup();
        return [
            "id" => $this->getId(),
            "groupId" => $group ? $group->getId() : null,
            "begin" => $this->getBegin()->getTimestamp(),
            "end" => $this->getEnd()->getTimestamp(),
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
}
