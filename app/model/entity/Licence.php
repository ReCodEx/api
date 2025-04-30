<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class Licence implements JsonSerializable
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
     * @ORM\ManyToOne(targetEntity="Instance", inversedBy="licences")
     * @var Instance
     */
    protected $instance;

    public function getInstance(): ?Instance
    {
        return $this->instance->isDeleted() ? null : $this->instance;
    }

    /**
     * A licence can be manually marked as invalid by the admins.
     * @ORM\Column(type="boolean")
     */
    protected $isValid;

    /**
     * The very last date on which this licence is valid (unless invalidated manually)
     * @ORM\Column(type="datetime")
     * @var DateTime
     */
    protected $validUntil;

    /**
     * Checks if the licence is valid at a given moment - by default right now.
     * @param DateTime $when When the licence should have been valid.
     * @return bool
     */
    public function isValid(?DateTime $when = null)
    {
        if ($when === null) {
            $when = new DateTime();
        }
        return $this->isValid && $this->validUntil >= $when;
    }

    /**
     * Internal note for the license.
     * @ORM\Column(type="string")
     */
    protected $note;

    public function jsonSerialize(): mixed
    {
        return [
            "id" => $this->getId(),
            "note" => $this->note,
            "isValid" => $this->isValid,
            "validUntil" => $this->validUntil->getTimestamp()
        ];
    }

    public static function createLicence(string $note, DateTime $validUntil, Instance $instance, bool $isValid = true)
    {
        $licence = new Licence();
        $licence->note = $note;
        $licence->validUntil = $validUntil;
        $licence->isValid = $isValid;
        $licence->instance = $instance;
        $instance->addLicence($licence);
        return $licence;
    }

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
    }

    public function getValidUntil(): DateTime
    {
        return $this->validUntil;
    }

    public function setValidUntil(DateTime $validUntil): void
    {
        $this->validUntil = $validUntil;
    }

    public function setIsValid(bool $isValid): void
    {
        $this->isValid = $isValid;
    }
}
