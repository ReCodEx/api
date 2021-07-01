<?php

namespace App\Model\Repository;

use App\Model\Entity\Instance;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @method Instance findOrThrow($id)
 */
class Instances extends BaseSoftDeleteRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, Instance::class);
    }
}
