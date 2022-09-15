<?php

namespace App\Model\Repository;

use DateTime;
use App\Model\Entity\Group;
use App\Model\Entity\GroupInvitation;
use App\Model\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<GroupInvitation>
 */
class GroupInvitations extends BaseRepository
{

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, GroupInvitation::class);
    }
}
