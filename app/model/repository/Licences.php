<?php

namespace App\Model\Repository;

use App\Model\Entity\Licence;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @method Licence findOrThrow($id)
 */
class Licences extends BaseRepository
{

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, Licence::class);
    }
}
