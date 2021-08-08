<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class Notification implements JsonSerializable
{
    use CreateableEntity;

    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
     */
    protected $id;

    /**
     * @var User
     * @ORM\ManyToOne(targetEntity="User")
     */
    protected $author;

    public function getAuthor(): ?User
    {
        return $this->author->isDeleted() ? null : $this->author;
    }

    /**
     * @var DateTime
     * @ORM\Column(type="datetime")
     */
    protected $visibleFrom;

    /**
     * @var DateTime
     * @ORM\Column(type="datetime")
     */
    protected $visibleTo;

    /**
     * @var Collection|Selectable
     * @ORM\ManyToMany(targetEntity="LocalizedNotification", indexBy="locale", cascade={"persist"})
     */
    protected $localizedTexts;

    public function getLocalizedTexts(): Collection
    {
        return $this->localizedTexts;
    }

    public function addLocalizedText(LocalizedNotification $localizedText)
    {
        $this->localizedTexts->add($localizedText);
    }

    /**
     * Get localized text based on given locale.
     * @param string $locale
     * @return LocalizedNotification|null
     */
    public function getLocalizedTextByLocale(string $locale): ?LocalizedEntity
    {
        $criteria = Criteria::create()->where(Criteria::expr()->eq("locale", $locale));
        $first = $this->localizedTexts->matching($criteria)->first();
        return $first === false ? null : $first;
    }

    /**
     * @ORM\ManyToMany(targetEntity="Group")
     */
    protected $groups;

    /**
     * @return Collection
     */
    public function getGroups()
    {
        return $this->groups->filter(
            function (Group $group) {
                return !$group->isDeleted();
            }
        );
    }

    public function addGroup(Group $group)
    {
        $this->groups->add($group);
    }

    /**
     * Get IDs of all assigned groups.
     * @return string[]
     */
    public function getGroupsIds()
    {
        return $this->getGroups()->map(
            function (Group $group) {
                return $group->getId();
            }
        )->getValues();
    }

    /**
     * @ORM\Column(type="string")
     */
    protected $role;

    /**
     * @ORM\Column(type="string")
     */
    protected $type;


    /**
     * Notification constructor.
     * @param User $author
     */
    public function __construct(
        User $author
    ) {
        $this->author = $author;
        $this->createdAt = new DateTime();
        $this->visibleFrom = new DateTime();
        $this->visibleTo = new DateTime();
        $this->localizedTexts = new ArrayCollection();
        $this->groups = new ArrayCollection();
    }

    public function jsonSerialize()
    {
        return [
            "id" => $this->id,
            "authorId" => $this->getAuthor() ? $this->getAuthor()->getId() : null,
            "createdAt" => $this->createdAt->getTimestamp(),
            "visibleFrom" => $this->visibleFrom->getTimestamp(),
            "visibleTo" => $this->visibleTo->getTimestamp(),
            "localizedTexts" => $this->localizedTexts->getValues(),
            "groupsIds" => $this->getGroupsIds(),
            "role" => $this->role,
            "type" => $this->type
        ];
    }

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getVisibleFrom(): DateTime
    {
        return $this->visibleFrom;
    }

    public function setVisibleFrom(DateTime $visibleFrom): void
    {
        $this->visibleFrom = $visibleFrom;
    }

    public function getVisibleTo(): DateTime
    {
        return $this->visibleTo;
    }

    public function setVisibleTo(DateTime $visibleTo): void
    {
        $this->visibleTo = $visibleTo;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }
}
