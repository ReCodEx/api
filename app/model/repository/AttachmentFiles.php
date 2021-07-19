<?php

namespace App\Model\Repository;

use App\Model\Entity\AttachmentFile;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseRepository<AttachmentFile>
 */
class AttachmentFiles extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, AttachmentFile::class);
    }

    /**
     * Find attachment files that are not assigned to any exercise nor assignment.
     * @return AttachmentFile[]
     */
    public function findUnused(): array
    {
        $query = $this->em->createQuery("
            SELECT f FROM App\Model\Entity\AttachmentFile f
            WHERE NOT EXISTS
                (SELECT e FROM App\Model\Entity\Exercise e WHERE e MEMBER OF f.exercises AND e.deletedAt IS NULL)
            AND NOT EXISTS
                (SELECT a FROM App\Model\Entity\Assignment a WHERE a MEMBER OF f.assignments AND a.deletedAt IS NULL)
        ");

        return $query->getResult();
    }
}
