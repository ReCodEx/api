<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;

trait VersionableEntity
{

    /**
     * @ORM\Column(type="integer")
     */
    protected $version = 1;

    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * Increment version number.
     */
    public function incrementVersion()
    {
        $this->version++;
    }

    /**
     * A setter with strange name since arbitrary modifications of version are extremely rare.
     * Use incrementVersion() instead in regular cases.
     */
    public function overrideVersion(int $version): void
    {
        $this->version = $version;
    }
}
