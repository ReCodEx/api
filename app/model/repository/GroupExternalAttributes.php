<?php

namespace App\Model\Repository;

use App\Model\Entity\Group;
use App\Model\Entity\GroupExternalAttribute;
use App\Model\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<GroupExternalAttribute>
 */
class GroupExternalAttributes extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, GroupExternalAttribute::class);
    }
}
