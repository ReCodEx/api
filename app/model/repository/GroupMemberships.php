<?php

namespace App\Model\Repository;

use App\Model\Entity\GroupMembership;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<GroupMembership>
 */
class GroupMemberships extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, GroupMembership::class);
    }
}
