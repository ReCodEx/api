<?php

namespace App\Model\Repository;

use App\Model\Entity\SecurityEvent;
use App\Model\Entity\GroupExam;
use App\Model\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use DateTime;
use Exception;

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
