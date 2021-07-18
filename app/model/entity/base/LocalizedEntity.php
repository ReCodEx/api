<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\MappedSuperclass
 */
abstract class LocalizedEntity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $createdAt;

    /**
     * @ORM\Column(type="string")
     */
    protected $locale;

    public function __construct(string $locale)
    {
        $this->locale = $locale;
        $this->createdAt = new DateTime();
    }

    abstract public function equals(LocalizedEntity $entity): bool;

    abstract public function setCreatedFrom(LocalizedEntity $entity);

    ////////////////////////////////////////////////////////////////////////////

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
