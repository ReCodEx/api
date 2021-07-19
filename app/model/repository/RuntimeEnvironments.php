<?php

namespace App\Model\Repository;

use App\Model\Entity\RuntimeEnvironment;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<RuntimeEnvironment>
 */
class RuntimeEnvironments extends BaseRepository
{

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, RuntimeEnvironment::class);
    }
}
