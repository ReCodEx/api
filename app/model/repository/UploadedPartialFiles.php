<?php

namespace App\Model\Repository;

use App\Model\Entity\UploadedPartialFile;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @method UploadedPartialFile findOrThrow($id)
 */
class UploadedPartialFiles extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, UploadedPartialFile::class);
    }
}
