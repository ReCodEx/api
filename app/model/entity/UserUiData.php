<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * Entity holding user-specific data stored by the UI.
 */
class UserUiData
{
    public function __construct($data)
    {
        $this->setData($data);
    }

    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=\Ramsey\Uuid\Doctrine\UuidGenerator::class)
     * @var \Ramsey\Uuid\UuidInterface
     */
    protected $id;

    /**
     * Arbitrary user-specific JSON-structured data stored by the UI.
     * @ORM\Column(type="text")
     */
    protected $data;

    /**
     * Get parsed UI data structured as (nested) assoc array.
     * @return array
     */
    public function getData(): array
    {
        return json_decode($this->data, true);
    }

    /**
     * Set (overwrite) the UI data.
     * @param array|object $root Root of the structured data (must be JSON serializable)
     */
    public function setData($root)
    {
        $this->data = json_encode($root);
    }
}
