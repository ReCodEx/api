<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class SisValidTerm implements JsonSerializable
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
     * @ORM\Column(type="integer")
     */
    protected $year;

    /**
     * @ORM\Column(type="integer")
     */
    protected $term;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $beginning;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $end;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $advertiseUntil;

    public function __construct($year, $term)
    {
        $this->year = $year;
        $this->term = $term;
    }

    public function isComplete(): bool
    {
        return $this->beginning !== null && $this->end !== null;
    }

    /**
     * Should courses in the term be advertised to the students by ReCodEx clients?
     * @param DateTime $now
     * @return bool
     */
    public function isAdvertised(DateTime $now): bool
    {
        if (!$this->isComplete()) {
            return false;
        }

        $advertiseUntil = $this->advertiseUntil;
        if ($advertiseUntil === null) {
            $advertiseUntil = $this->end;
        }

        return $now >= $this->beginning && $now <= $advertiseUntil;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->getId(),
            'year' => $this->year,
            'term' => $this->term,
            'beginning' => $this->beginning ? $this->beginning->getTimestamp() : null,
            'end' => $this->end ? $this->end->getTimestamp() : null,
            'advertiseUntil' => $this->advertiseUntil ? $this->advertiseUntil->getTimestamp() : null
        ];
    }

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getTerm(): int
    {
        return $this->term;
    }

    public function getBeginning(): ?DateTime
    {
        return $this->beginning;
    }

    public function setBeginning(DateTime $beginning): void
    {
        $this->beginning = $beginning;
    }

    public function getEnd(): ?DateTime
    {
        return $this->end;
    }

    public function setEnd(DateTime $end): void
    {
        $this->end = $end;
    }

    public function getAdvertiseUntil(): ?DateTime
    {
        return $this->advertiseUntil;
    }

    public function setAdvertiseUntil(?DateTime $advertiseUntil): void
    {
        $this->advertiseUntil = $advertiseUntil;
    }
}
