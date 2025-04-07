<?php

namespace App\Model\Repository;

use App\Model\Entity\HardwareGroup;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<HardwareGroup>
 */
class HardwareGroups extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, HardwareGroup::class);
    }
}
