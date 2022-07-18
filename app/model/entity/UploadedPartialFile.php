<?php

namespace App\Model\Entity;

use App\Helpers\FileStorageManager;
use App\Helpers\FileStorage\IImmutableFile;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use DateTime;
use OverflowException;

/**
 * @ORM\Entity
 * This entity tracks an upload of a file per partes (chunked upload).
 * Once the file is uploaded completely, it will be converted into UploadedFile entity.
 */
class UploadedPartialFile implements JsonSerializable
{
    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * The name under which the file was uploaded
     * @ORM\Column(type="string")
     */
    protected $name;

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Extract extension from this file and return it.
     * @return string extension
     */
    public function getFileExtension(): string
    {
        return pathinfo($this->name, PATHINFO_EXTENSION);
    }

    /**
     * @ORM\Column(type="datetime")
     */
    protected $startedAt;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $updatedAt;

    /**
     * @ORM\Column(type="integer")
     */
    protected $totalSize;

    public function getTotalSize(): int
    {
        return $this->totalSize;
    }

    /**
     * @ORM\Column(type="integer")
     */
    protected $uploadedSize = 0;

    public function getUploadedSize(): int
    {
        return $this->uploadedSize;
    }

    /**
     * Whether all chunks have been uploaded.
     * @return bool
     */
    public function isUploadComplete(): bool
    {
        return $this->totalSize === $this->uploadedSize;
    }

    /**
     * @ORM\Column(type="integer")
     */
    protected $chunks = 0;

    public function getChunks(): int
    {
        return $this->chunks;
    }

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
     * @param int $totalSize Final size of the file
     * @param User $user The user who uploaded the file
     */
    public function __construct(string $name, int $totalSize, User $user)
    {
        $this->name = $name;
        $this->startedAt = new DateTime();
        $this->updatedAt = new DateTime();
        $this->totalSize = $totalSize;
        $this->user = $user;
    }

    /**
     * Update the record by registering another chunk that was uploaded.
     * @param int $chunkSize how many bytes was uploaded in the last chunk
     * @throws OverflowException
     */
    public function addChunk(int $chunkSize)
    {
        $this->uploadedSize += $chunkSize;
        if ($this->uploadedSize > $this->totalSize) {
            throw new OverflowException(
                "The upload size ($this->uploadedSize) exceeded declared total size ($this->totalSize)."
            );
        }

        ++$this->chunks;
        $this->updatedAt = new DateTime();
    }

    public function jsonSerialize()
    {
        return [
            "id" => $this->id,
            "name" => $this->name,
            "totalSize" => $this->totalSize,
            "uploadedSize" => $this->uploadedSize,
            "startedAt" => $this->startedAt->getTimestamp(),
            "updatedAt" => $this->updatedAt->getTimestamp(),
            "userId" => $this->getUser() ? $this->getUser()->getId() : null,
        ];
    }
}
