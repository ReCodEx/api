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
 * @method Group getGroup()
 * @method DateTime getCreatedAt()
 * @method int getMaxPoints()
 * @method int getVersion()
 * @method setIsPublic(bool $public)
 * @method setIsBonus(bool $bonus)
 * @method setMaxPoints(int $points)
 */
class ShadowAssignment
{
  use MagicAccessors;
  use UpdateableEntity;
  use DeleteableEntity;

  private function __construct(
    int $maxPoints,
    Group $group,
    bool $isPublic,
    bool $isBonus = false
  ) {
    $this->group = $group;
    $this->maxPoints = $maxPoints;
    $this->shadowAssignmentEvaluations = new ArrayCollection;
    $this->isPublic = $isPublic;
    $this->localizedTexts = new ArrayCollection();
    $this->version = 1;
    $this->isBonus = $isBonus;
    $this->createdAt = new \DateTime;
    $this->updatedAt = new \DateTime;
  }

  public static function assignToGroup(Group $group, $isPublic = false) {
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
   * @ORM\Column(type="integer")
   */
  protected $version;

  /**
   * Increment version number.
   */
  public function incrementVersion() {
    $this->version++;
  }

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isPublic;

  public function isPublic() {
    return $this->isPublic;
  }

  /**
   * @ORM\Column(type="boolean")
   */
  protected $isBonus;

  public function isBonus(): bool {
    return $this->isBonus;
  }

  /**
   * @ORM\Column(type="datetime")
   */
  protected $createdAt;

  /**
   * @ORM\ManyToMany(targetEntity="LocalizedExercise", indexBy="locale")
   * @var Collection|Selectable
   */
  protected $localizedTexts;

  public function getLocalizedTexts(): Collection {
    return $this->localizedTexts;
  }

  public function addLocalizedText(LocalizedExercise $localizedText) {
    $this->localizedTexts->add($localizedText);
  }

  /**
   * Get localized text based on given locale.
   * @param string $locale
   * @return LocalizedExercise|null
   */
  public function getLocalizedTextByLocale(string $locale) {
    $criteria = Criteria::create()->where(Criteria::expr()->eq("locale", $locale));
    $first = $this->localizedTexts->matching($criteria)->first();
    return $first === false ? null : $first;
  }

  /**
   * @ORM\Column(type="integer")
   */
  protected $maxPoints;

  /**
   * Assignment can be marked as bonus, then we do not want to add its points
   * to overall maximum points of group. This function will return 0 if
   * assignment is marked as bonus one, otherwise it will return result of
   * $this->getMaxPoints() function.
   * @return int
   */
  public function getGroupPoints(): int {
    if ($this->isBonus) {
      return 0;
    } else {
      return $this->getMaxPoints();
    }
  }

  /**
   * @ORM\ManyToOne(targetEntity="Group", inversedBy="shadowAssignments")
   */
  protected $group;

  /**
   * @ORM\OneToMany(targetEntity="ShadowAssignmentEvaluation", mappedBy="assignment")
   */
  protected $shadowAssignmentEvaluations;

}
