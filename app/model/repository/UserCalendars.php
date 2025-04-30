<?php

namespace App\Model\Repository;

use App\Model\Entity\UserCalendar;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<UserCalendar>
 */
class UserCalendars extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, UserCalendar::class);
    }
}
