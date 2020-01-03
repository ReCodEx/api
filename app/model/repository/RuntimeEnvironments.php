<?php

namespace App\Model\Repository;

use App\Model\Entity\RuntimeEnvironment;
use Kdyby\Doctrine\EntityManager;

class RuntimeEnvironments extends BaseRepository
{

    public function __construct(EntityManager $em)
    {
        parent::__construct($em, RuntimeEnvironment::class);
    }
}
