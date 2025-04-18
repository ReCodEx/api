<?php

namespace App\Model\Repository;

use App\Model\Entity\Group;
use App\Model\Entity\GroupExam;
use Doctrine\ORM\EntityManagerInterface;
use DateTime;
use Exception;

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
     * Fetch group exam entity by group-begin index. If not present, null is returned.
     * @param Group $group
     * @return GroupExam|null
     */
    public function findPendingForGroup(Group $group): ?GroupExam
    {
        $begin = $group->getExamBegin();
        if (!$begin || !$group->hasExamPeriodSet()) {
            return null;
        }

        $exam = $this->findBy(["group" => $group, "begin" => $begin]);
        if (count($exam) > 1) {
            throw new Exception("Data corruption, there is more than one group exam starting at the same time.");
        }

        return $exam ? reset($exam) : null;
    }

    /**
     * Fetch group exam entity by group-begin index. If not present, new entity is created.
     * @param Group $group
     * @param DateTime|null $begin if null, exam begin from the group is taken
     * @param DateTime|null $end if null, exam end from the group is taken
     * @param bool|null $strict if null, examLockStrict value is taken
     * @return GroupExam
     */
    public function findOrCreate(
        Group $group,
        ?DateTime $begin = null,
        ?DateTime $end = null,
        ?bool $strict = null
    ): GroupExam {
        $begin = $begin ?? $group->getExamBegin();
        $end = $end ?? $group->getExamEnd();
        $strict = $strict === null ? $group->isExamLockStrict() : $strict;

        $exam = $this->findBy(["group" => $group, "begin" => $begin]);
        if (count($exam) > 1) {
            throw new Exception("Data corruption, there is more than one group exam starting at the same time.");
        }

        if (!$exam) {
            $exam = new GroupExam($group, $begin, $end, $strict);
            $this->persist($exam);
        } else {
            $exam = reset($exam);
        }

        return $exam;
    }
}
