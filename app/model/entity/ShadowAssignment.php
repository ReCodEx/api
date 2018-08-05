<?php

namespace App\Model\Entity;

use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Kdyby\Doctrine\MagicAccessors\MagicAccessors;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false)
 *
 * @method string getId()
 * @method Collection getShadowAssignmentPointsCollection()
 * @method setMaxPoints(int $points)
 */
class ShadowAssignment extends AssignmentBase
{
  use MagicAccessors;

  private function __construct(
    int $maxPoints,
    Group $group,
    bool $isPublic,
    bool $isBonus = false
  ) {
    $this->group = $group;
    $this->maxPoints = $maxPoints;
    $this->shadowAssignmentPointsCollection = new ArrayCollection();
    $this->isPublic = $isPublic;
    $this->localizedTexts = new ArrayCollection();
    $this->version = 1;
    $this->isBonus = $isBonus;
    $this->createdAt = new \DateTime();
    $this->updatedAt = new \DateTime();
  }

  public static function createInGroup(Group $group, $isPublic = false) {
    $assignment = new self(
      0,
      $group,
      $isPublic,
      false
    );

    $group->addShadowAssignment($assignment);
    return $assignment;
  }

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\ManyToMany(targetEntity="LocalizedShadowAssignment", indexBy="locale")
   * @var Collection|Selectable
   */
  protected $localizedTexts;

  public function getLocalizedTexts(): Collection {
    return $this->localizedTexts;
  }

  public function addLocalizedText(LocalizedShadowAssignment $localizedText) {
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
   * @ORM\Column(type="integer")
   */
  protected $maxPoints;

  public function getMaxPoints(): int {
    return $this->maxPoints;
  }

  /**
   * @ORM\ManyToOne(targetEntity="Group", inversedBy="shadowAssignments")
   */
  protected $group;

  public function getGroup(): Group {
    return $this->group;
  }

  /**
   * @ORM\OneToMany(targetEntity="ShadowAssignmentPoints", mappedBy="shadowAssignment")
   */
  protected $shadowAssignmentPointsCollection;

  public function getPointsByUser(User $user) {
    $criteria = Criteria::create()->where(Criteria::expr()->eq("awardee", $user));
    $first = $this->shadowAssignmentPointsCollection->matching($criteria)->first();
    return $first === false ? null : $first;
  }

}
