<?php

namespace App\Model\Repository;

use App\Model\Entity\GroupInvitation;
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
