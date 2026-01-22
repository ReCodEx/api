<?php

namespace App\Model\Repository;

use App\Model\Entity\Group;
use App\Model\Entity\GroupExam;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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
     * Internal helper that tries to find the exam or create it if not present.
     * It returns null if creation failed due to race condition (expects retry by caller).
     * @param Group $group
     * @param DateTime $begin
     * @param DateTime $end
     * @param bool $strict
     * @return GroupExam|null
     */
    private function tryFindOrCreate(Group $group, DateTime $begin, DateTime $end, bool $strict): ?GroupExam
    {
        $exam = $this->findBy(["group" => $group, "begin" => $begin]);
        if (count($exam) > 1) {
            throw new Exception("Data corruption, there is more than one group exam starting at the same time.");
        }

        if (!$exam) {
            try {
                $this->em->getConnection()->executeQuery(
                    "INSERT INTO group_exam (group_id, begin, end, lock_strict) VALUES (:gid, :begin, :end, :strict)",
                    [
                        'gid' => $group->getId(),
                        'begin' => $begin->format('Y-m-d H:i:s'),
                        'end' => $end->format('Y-m-d H:i:s'),
                        'strict' => $strict ? 1 : 0
                    ]
                );
            } catch (UniqueConstraintViolationException) {
                // race condition, another transaction created the entity meanwhile
            }
            return null; // signal caller to retry
        } else {
            $exam = reset($exam);
        }

        return $exam;
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

        for ($retries = 0; $retries < 3; $retries++) {
            $exam = $this->tryFindOrCreate($group, $begin, $end, $strict);
            if ($exam !== null) {
                return $exam;
            }
        }

        throw new Exception("Failed to find or create group exam after multiple attempts.");
    }
}
