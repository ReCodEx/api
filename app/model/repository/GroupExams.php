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
}
