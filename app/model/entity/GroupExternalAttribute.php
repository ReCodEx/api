<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Exceptions\InvalidArgumentException;
use Nette\Utils\Validators;
use JsonSerializable;

/**
 * @ORM\Entity
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(columns={"group_id", "service", "key", "value"})},
 *            indexes={@ORM\Index(name="keys_idx", columns={"service", "key"})})
 *
 * Key-value attributes assigned to groups to connect them to 3rd party systems to simplify
 * external group management (creation, archiving) and student membership management.
 */
class GroupExternalAttribute implements JsonSerializable
{
    use CreateableEntity;

    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=\Ramsey\Uuid\Doctrine\UuidGenerator::class)
     * @var \Ramsey\Uuid\UuidInterface
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="Group", inversedBy="externalAttributes")
     */
    protected $group;

    /**
     * @ORM\Column(type="string", length=32)
     * Identifies 3rd party service which assigned the attribute. This is basically a namespace for keys.
     */
    protected $service;

    /**
     * @ORM\Column(type="string", length=32)
     * Key of the attribute under which it can be searched.
     */
    protected $key;

    /**
     * @ORM\Column(type="string")
     */
    protected $value;

    /**
     * Constructor initializes all fields.
     * @param Group $group
     * @param string $service
     * @param string $key
     * @param string $value
     */
    public function __construct(Group $group, string $service, string $key, string $value)
    {
        $this->group = $group;
        $this->service = $service;
        $this->key = $key;
        $this->value = $value;
        $this->createdNow();
    }

    public function jsonSerialize(): mixed
    {
        return [
            "id" => $this->getId(),
            "createdAt" => $this->createdAt->getTimestamp(),
            "group" => $this->getGroup()->getId(),
            "service" => $this->getService(),
            "key" => $this->getKey(),
            "value" => $this->getValue(),
        ];
    }

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
    }

    public function getGroup(): Group
    {
        return $this->group;
    }

    public function getService(): string
    {
        return $this->service;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
