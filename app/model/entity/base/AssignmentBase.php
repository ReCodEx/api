<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;

/**
 * @ORM\MappedSuperclass
 */
abstract class AssignmentBase
{
    use CreateableEntity;
    use VersionableEntity;
    use UpdateableEntity;
    use DeleteableEntity;

    abstract function getGroup(): ?Group;

    abstract function getMaxPoints(): int;

    abstract function getLocalizedTexts(): Collection;

    abstract function getLocalizedTextByLocale(string $locale): ?LocalizedEntity;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isPublic;

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isBonus;

    public function isBonus(): bool
    {
        return $this->isBonus;
    }

    /**
     * Assignment can be marked as bonus, then we do not want to add its points
     * to overall maximum points of group. This function will return 0 if
     * assignment is marked as bonus one, otherwise it will return result of
     * $this->getMaxPoints() function.
     * @return int
     */
    public function getGroupPoints(): int
    {
        if ($this->isBonus) {
            return 0;
        } else {
            return $this->getMaxPoints();
        }
    }

    ////////////////////////////////////////////////////////////////////////////

    public function setIsPublic(bool $isPublic)
    {
        $this->isPublic = $isPublic;
    }

    public function setIsBonus(bool $isBonus)
    {
        $this->isBonus = $isBonus;
    }
}
