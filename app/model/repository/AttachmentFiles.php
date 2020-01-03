<?php

namespace App\Model\Repository;

use App\Model\Entity\AttachmentFile;
use Kdyby\Doctrine\EntityManager;

class AttachmentFiles extends BaseRepository
{
    public function __construct(EntityManager $em)
    {
        parent::__construct($em, AttachmentFile::class);
    }
}
