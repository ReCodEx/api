<?php

namespace App\Model\Repository;

use DateTime;
use App\Model\Entity\Group;
use App\Model\Entity\GroupExam;
use App\Model\Entity\GroupExamLock;
use App\Model\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;

/**
 * @extends BaseRepository<GroupExamLock>
 */
class GroupExamLocks extends BaseRepository
{
    /** @var GroupExams */
    private $groupExams;

    public function __construct(EntityManagerInterface $em, GroupExams $groupExams)
    {
        parent::__construct($em, GroupExamLock::class);
        $this->groupExams = $groupExams;
    }

    public function getCurrentLock(User $user): ?GroupExamLock
    {
        $group = $user->getGroupLock();
        if (!$group) {
            return null;
        }

        $exam = $this->groupExams->findPendingForGroup($group);
        if (!$exam) {
            return null;
        }

        $locks = $this->findBy(
            [ 'student' => $user, 'groupExam' => $exam, 'unlockedAt' => null ],
            [ 'createdAt' => 'DESC' ] // first one is the last one created
        );
        return $locks ? $locks[0] : null;
    }
}
