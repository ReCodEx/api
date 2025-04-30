<?php

namespace App\Model\Repository;

use App\Model\Entity\Licence;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<Licence>
 */
class Licences extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, Licence::class);
    }
}
