<?php

namespace App\Model\Repository;

use App\Model\Entity\SecurityEvent;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<SecurityEvent>
 */
class SecurityEvents extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, SecurityEvent::class);
    }
}
