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
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=\Ramsey\Uuid\Doctrine\UuidGenerator::class)
     * @var \Ramsey\Uuid\UuidInterface
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
        return pathinfo($this->name, PATHINFO_EXTENSION);
    }

    /**
     * @ORM\Column(type="datetime")
     */
    protected $uploadedAt;

    /**
     * @ORM\Column(type="boolean")
     * If true, the file is accessible to all logged-in users.
     * This is useful for files associated with entries like exercises.
     */
    protected $isPublic;

    /**
     * External identification (used in URL) that allows for accessing the file without authentication.
     * If empty, the file is not accessible this way.
     * The identifier should be a generated random string.
     * @ORM\Column(type="string", length=16, nullable=true, unique=true)
     */
    protected $external = null;

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
        return $this->user && !$this->user->isDeleted() ? $this->user : null;
    }

    public function getUserId(): ?string
    {
        return $this->user && !$this->user->isDeleted() ? $this->user->getId() : null;
    }

    public function getUserIdEvenIfDeleted(): ?string
    {
        return $this->user ? $this->user->getId() : null;
    }

    /**
     * @param string $name Name of the file
     * @param DateTime $uploadedAt Time of the upload
     * @param int $fileSize Size of the file
     * @param User|null $user The user who uploaded the file
     * @param bool $isPublic
     */
    public function __construct(
        string $name,
        DateTime $uploadedAt,
        int $fileSize,
        ?User $user,
        $isPublic = false
    ) {
        $this->name = $name;
        $this->uploadedAt = $uploadedAt;
        $this->fileSize = $fileSize;
        $this->user = $user;
        $this->isPublic = $isPublic;
    }

    public function jsonSerialize(): mixed
    {
        return [
            "id" => $this->getId(),
            "name" => $this->name,
            "size" => $this->fileSize,
            "uploadedAt" => $this->uploadedAt->getTimestamp(),
            "userId" => $this->getUserId(),
            "isPublic" => $this->isPublic
        ];
    }

    public function isPublic()
    {
        return $this->isPublic;
    }

    public function setPublic(bool $isPublic): void
    {
        $this->isPublic = $isPublic;
    }

    public function getExternal(): ?string
    {
        return $this->external;
    }

    public function setExternal(?string $external): void
    {
        $this->external = $external;
    }

    /**
     * Generate a random external ID for this file which uses only URL-safe characters.
     */
    public function generateRandomExternalId(): void
    {
        // the + and / characters are replaced to make the string URL-safe without the need for encoding
        // (base64 encoding uses A-Z, a-z, 0-9, +, /)
        $this->external = str_replace(['+', '/'], ['-', '$'], base64_encode(random_bytes(12)));
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

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
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
