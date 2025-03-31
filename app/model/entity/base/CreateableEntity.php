<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

trait CreatableEntity
{
    /**
     * @ORM\Column(type="datetime")
     * @var DateTime
     */
    protected $createdAt;

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function createdNow(): void
    {
        $this->createdAt = new DateTime();
    }

    /**
     * Special setter used for rare cases. Use createdNow() for common usage.
     */
    public function overrideCreatedAt(DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
