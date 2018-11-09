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
 * @method User getAuthor()
 * @method DateTime getCreatedAt()
 * @method DateTime getVisibleFrom()
 * @method DateTime getVisibleTo()
 * @method Collection getLocalizedTexts()
 * @method string getRole()
 * @method string getType()
 */
class Notification implements JsonSerializable
{
  use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;

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

  /**
   * @var DateTime
   * @ORM\Column(type="datetime")
   */
  protected $createdAt;

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
   * @ORM\ManyToMany(targetEntity="LocalizedExercise", indexBy="locale")
   */
  protected $localizedTexts;

  public function addLocalizedText(LocalizedExercise $localizedText) {
    $this->localizedTexts->add($localizedText);
  }

  /**
   * Get localized text based on given locale.
   * @param string $locale
   * @return LocalizedExercise|null
   */
  public function getLocalizedTextByLocale(string $locale): ?LocalizedEntity {
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
  public function getGroups() {
    return $this->groups->filter(function (Group $group) {
      return !$group->isDeleted();
    });
  }

  /**
   * Get IDs of all assigned groups.
   * @return string[]
   */
  public function getGroupsIds() {
    return $this->getGroups()->map(function(Group $group) {
      return $group->getId();
    })->getValues();
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
   * @param DateTime $createdAt
   * @param DateTime $visibleFrom
   * @param DateTime $visibleTo
   * @param Collection|Selectable $localizedTexts
   * @param string $role
   * @param string $type
   * @param Group|null $group
   */
  public function __construct(
    User $author,
    DateTime $createdAt,
    DateTime $visibleFrom,
    DateTime $visibleTo,
    $localizedTexts,
    string $role,
    string $type,
    Group $group = null
  ) {
    $this->author = $author;
    $this->createdAt = $createdAt;
    $this->visibleFrom = $visibleFrom;
    $this->visibleTo = $visibleTo;
    $this->localizedTexts = $localizedTexts;
    $this->role = $role;
    $this->type = $type;
    $this->groups = $group ? new ArrayCollection($group) : new ArrayCollection();
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "authorId" => $this->author->getId(),
      "createdAt" => $this->createdAt->getTimestamp(),
      "visibleFrom" => $this->visibleFrom->getTimestamp(),
      "visibleTo" => $this->visibleTo->getTimestamp(),
      "localizedTexts" => $this->localizedTexts->getValues(),
      "groupsIds" => $this->getGroupsIds(),
      "type" => $this->type
    ];
  }
}
