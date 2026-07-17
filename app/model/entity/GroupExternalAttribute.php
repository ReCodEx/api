<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

#[ORM\Table]
#[ORM\Index(name: 'keys_idx', columns: ['service', 'key'])]
#[ORM\UniqueConstraint(columns: ['group_id', 'service', 'key', 'value'])]
#[ORM\Entity]
class GroupExternalAttribute implements JsonSerializable
{
    use CreatableEntity;

    /**
     * @var \Ramsey\Uuid\UuidInterface
     */
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: \Ramsey\Uuid\Doctrine\UuidGenerator::class)]
    protected $id;

    #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: 'externalAttributes')]
    protected $group;

    /**
     * Identifies 3rd party service which assigned the attribute. This is basically a namespace for keys.
     */
    #[ORM\Column(type: 'string', length: 32)]
    protected $service;

    /**
     * Key of the attribute under which it can be searched.
     */
    #[ORM\Column(name: '`key`', type: 'string', length: 32)]
    protected $key;

    #[ORM\Column(type: 'string')]
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
