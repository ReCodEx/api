<?php

namespace App\Model\Entity;

use App\Helpers\EntityMetadata\HwGroupMeta;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * @ORM\Entity
 * @method string getId()
 * @method string getName()
 * @method string getDescription()
 */
class HardwareGroup implements JsonSerializable
{
    use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;

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
     * @ORM\Column(type="text")
     */
    protected $description;

    /**
     * @ORM\Column(type="text")
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

    public function jsonSerialize()
    {
        return [
            "id" => $this->id,
            "name" => $this->name,
            "description" => $this->description,
            "metadata" => $this->getMetadata()->toArray()
        ];
    }
}
