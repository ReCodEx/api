<?php

namespace App\Model\Repository;

use DateTime;
use App\Model\Entity\Group;
use App\Model\Entity\GroupExamLock;
use App\Model\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<GroupExamLock>
 */
class GroupExamLocks extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, GroupExamLock::class);
    }
}
