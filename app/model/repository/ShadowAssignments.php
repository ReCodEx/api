<?php

namespace App\Model\Repository;

use App\Model\Entity\ShadowAssignment;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @method ShadowAssignment findOrThrow($id)
 */
class ShadowAssignments extends BaseSoftDeleteRepository
{

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, ShadowAssignment::class);
    }
}
