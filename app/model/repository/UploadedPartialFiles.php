<?php

namespace App\Model\Repository;

use App\Model\Entity\UploadedPartialFile;
use Doctrine\ORM\EntityManagerInterface;
use DateTime;

/**
 * @extends BaseRepository<UploadedPartialFile>
 */
class UploadedPartialFiles extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, UploadedPartialFile::class);
    }

    /**
     * Get an array of IDs of all partial file uploads.
     * @return string[]
     */
    public function getAllIds(): array
    {
        return array_map(function ($file) {
            return $file->getId();
        }, $this->findAll());
    }

    /**
     * Find uploaded partial files that are too old and not completed.
     * @param DateTime $now Current date
     * @param string $threshold Maximum allowed age of uploaded files
     *                          (in a form acceptable by DateTime::modify after prefixing with a "-" sign)
     * @return UploadedPartialFile[]
     */
    public function findUnfinished(DateTime $now, string $threshold): array
    {
        $thresholdDate = clone $now;
        $thresholdDate->modify("-" . $threshold);

        $query = $this->em->createQuery("
            SELECT f FROM App\Model\Entity\UploadedPartialFile f
            WHERE f.updatedAt < :threshold
        ");
        $query->setParameters([ "threshold" => $thresholdDate ]);

        return $query->getResult();
    }
}
