<?php

namespace App\Model\Repository;

use DateTime;
use App\Model\Entity\Group;
use App\Model\Entity\GroupExam;
use App\Model\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<GroupExam>
 */
class GroupExams extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, GroupExam::class);
    }

    /**
     * Fetch group exam entity by group-begin index. If not present, new entity is created.
     * @param Group $group
     * @param DateTime|null $begin if null, exam begin from the group is taken
     * @param DateTime|null $end if null, exam end from the group is taken
     * @return GroupExam
     */
    public function findOrCreate(Group $group, DateTime $begin = null, DateTime $end = null): GroupExam
    {
        $begin = $begin ?? $group->getExamBegin();
        $exam = $this->findBy(["group" => $group, "begin" => $begin]);
        if (!$exam) {
            $exam = new GroupExam($group, $begin, $end ?? $group->getExamEnd());
            $this->persist($exam);
        }
        return $exam;
    }
}
