<?php

namespace App\Model\Entity;

use App\Helpers\FileStorageManager;
use App\Helpers\FileStorage\IImmutableFile;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use DateTime;

/**
 * @ORM\Entity
 * A special case of solution file which is used when the user submits single ZIP archive.
 * In such case it would be impractical to put it into another ZIP archive.
 */
class SolutionZipFile extends SolutionFile implements JsonSerializable
{
    public function __construct($name, DateTime $uploadedAt, $fileSize, ?User $user, Solution $solution)
    {
        parent::__construct($name, $uploadedAt, $fileSize, $user, $solution);
    }

    public static function fromUploadedFile(UploadedFile $file, Solution $solution)
    {
        return new self(
            $file->getName(),
            $file->getUploadedAt(),
            $file->getFileSize(),
            $file->getUser(),
            $solution
        );
    }

    public function getFile(FileStorageManager $manager): ?IImmutableFile
    {
        return $manager->getSolutionFile($this->getSolution()); // this will return entire archive
    }

    private $zipEntries = null;

    public function prepareExtendedSerializationData(FileStorageManager $manager): void
    {
        $file = $this->getFile($manager);
        if ($file) {
            $this->zipEntries = $file->getZipEntries();
        }
    }

    /**
     * Includes entries of the ZIP file (since the user might want to access them).
     */
    public function jsonSerialize()
    {
        $json = parent::jsonSerialize();
        if ($this->zipEntries) {
            $json['zipEntries'] = $this->zipEntries;
        }
        return $json;
    }
}
