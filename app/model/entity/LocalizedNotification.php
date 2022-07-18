<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class LocalizedNotification extends LocalizedEntity implements JsonSerializable
{

    /**
     * @ORM\Column(type="text")
     */
    protected $text;

    /**
     * @ORM\ManyToOne(targetEntity="LocalizedNotification")
     * @ORM\JoinColumn(onDelete="SET NULL")
     */
    protected $createdFrom;


    public function __construct($locale, string $text, ?LocalizedNotification $createdFrom = null)
    {
        parent::__construct($locale);
        $this->text = $text;
        $this->createdFrom = $createdFrom;
    }


    public function equals(LocalizedEntity $entity): bool
    {
        return $entity instanceof LocalizedNotification
            && $this->text === $entity->getText();
    }

    public function setCreatedFrom(LocalizedEntity $entity)
    {
        if ($entity instanceof LocalizedNotification) {
            $this->createdFrom = $entity;
        }
    }

    public function jsonSerialize(): mixed
    {
        return [
            "id" => $this->getId(),
            "locale" => $this->locale,
            "text" => $this->text,
            "createdAt" => $this->createdAt->getTimestamp(),
        ];
    }

    /*
     * Accessors
     */

    public function getCreatedFrom(): ?LocalizedNotification
    {
        return $this->createdFrom;
    }

    public function getText(): string
    {
        return $this->text;
    }
}
