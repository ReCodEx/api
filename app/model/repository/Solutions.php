<?php

namespace App\Model\Repository;

use App\Model\Entity\Solution;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<Solution>
 */
class Solutions extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, Solution::class);
    }
}
