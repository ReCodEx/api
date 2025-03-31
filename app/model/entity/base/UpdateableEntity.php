<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

trait UpdatableEntity
{
    /**
     * @ORM\Column(type="datetime")
     * @var DateTime
     */
    protected $updatedAt;

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    public function updatedNow(): void
    {
        $this->updatedAt = new DateTime();
    }

    /**
     * Special setter used for rare cases. Use updatedNow() for common usage.
     */
    public function overrideUpdatedAt(DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
