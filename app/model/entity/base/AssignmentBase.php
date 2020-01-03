<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Kdyby\Doctrine\MagicAccessors\MagicAccessors;

/**
 * @ORM\MappedSuperclass
 *
 * @method DateTime getCreatedAt()
 * @method setIsPublic(bool $public)
 * @method setIsBonus(bool $bonus)
 */
abstract class AssignmentBase
{
    use MagicAccessors;
    use VersionableEntity;
    use UpdateableEntity;
    use DeleteableEntity;

    abstract function getGroup(): ?Group;

    abstract function getMaxPoints(): int;

    abstract function getLocalizedTexts(): Collection;

    abstract function getLocalizedTextByLocale(string $locale): ?LocalizedEntity;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $createdAt;

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
}
