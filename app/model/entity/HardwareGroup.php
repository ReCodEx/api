<?php

namespace App\Model\Entity;

use App\Helpers\EntityMetadata\HwGroupMeta;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class HardwareGroup implements JsonSerializable
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=32)
     */
    protected $id;

    /**
     * @ORM\Column(type="string")
     */
    protected $name;

    /**
     * @ORM\Column(type="string", length=1024)
     */
    protected $description;

    /**
     * @ORM\Column(type="text", length=65535)
     */
    protected $metadata;


    public function __construct(
        string $id,
        string $name,
        string $description,
        string $metadata
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->metadata = $metadata;
    }

    public function getMetadataString(): string
    {
        return $this->metadata;
    }

    public function getMetadata(): HwGroupMeta
    {
        return new HwGroupMeta($this->metadata);
    }

    public function jsonSerialize(): mixed
    {
        return [
            "id" => $this->getId(),
            "name" => $this->name,
            "description" => $this->description,
            "metadata" => $this->getMetadata()->toArray()
        ];
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

    public function getDescription(): string
    {
        return $this->description;
    }
}
