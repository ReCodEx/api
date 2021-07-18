<?php

namespace App\Model\Entity;

use App\Helpers\FileStorageManager;
use App\Helpers\FileStorage\IImmutableFile;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use DateTime;

/**
 * @ORM\Entity
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn("discriminator")
 * @ORM\Table(name="`uploaded_file`")
 */
class UploadedFile implements JsonSerializable
{
    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * The name under which the file was uploaded
     * @ORM\Column(type="string")
     */
    protected $name;

    /**
     * Extract extension from this file and return it.
     * @return string extension
     */
    public function getFileExtension(): string
    {
        return pathinfo($this->name, PATHINFO_EXTENSION) ?? "";
    }

    /**
     * @ORM\Column(type="datetime")
     */
    protected $uploadedAt;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isPublic;

    /**
     * @ORM\Column(type="integer")
     */
    protected $fileSize;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     */
    protected $user;

    public function getUser(): ?User
    {
        return $this->user->isDeleted() ? null : $this->user;
    }

    public function getUserIdEvenIfDeleted(): string
    {
        return $this->user->getId();
    }

    /**
     * @param string $name Name of the file
     * @param DateTime $uploadedAt Time of the upload
     * @param int $fileSize Size of the file
     * @param User $user The user who uploaded the file
     * @param bool $isPublic
     */
    public function __construct(
        string $name,
        DateTime $uploadedAt,
        int $fileSize,
        User $user,
        $isPublic = false
    ) {
        $this->name = $name;
        $this->uploadedAt = $uploadedAt;
        $this->fileSize = $fileSize;
        $this->user = $user;
        $this->isPublic = $isPublic;
    }

    public function jsonSerialize()
    {
        return [
            "id" => $this->id,
            "name" => $this->name,
            "size" => $this->fileSize,
            "uploadedAt" => $this->uploadedAt->getTimestamp(),
            "userId" => $this->getUser() ? $this->getUser()->getId() : null,
            "isPublic" => $this->isPublic
        ];
    }

    public function isPublic()
    {
        return $this->isPublic;
    }

    /**
     * Retrieve a corresponding file object from file storage.
     * @param FileStorageManager $manager the storage that retrieves the file
     * @return IImmutableFile|null file object wrapper, null if the file is missing (e.g., was deleted)
     */
    public function getFile(FileStorageManager $manager): ?IImmutableFile
    {
        return $manager->getUploadedFile($this);
    }

    ////////////////////////////////////////////////////////////////////////////

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUploadedAt(): DateTime
    {
        return $this->uploadedAt;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }
}
