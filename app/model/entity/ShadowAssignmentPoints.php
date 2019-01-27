<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Kdyby\Doctrine\MagicAccessors\MagicAccessors;
use DateTime;

/**
 * @ORM\Entity
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(columns={"shadow_assignment_id", "awardee_id"})})
 *
 * @method string getId()
 * @method string getNote()
 * @method int getPoints()
 * @method DateTime getCreatedAt()
 * @method ?DateTime getAwardedAt()
 * @method setPoints(int $points)
 * @method setNote(string $note)
 * @method void setAwardedAt(?DateTime $awardedAt)
 */
class ShadowAssignmentPoints
{
  use MagicAccessors;
  use UpdateableEntity;

  public function __construct(int $points, string $note, ShadowAssignment $shadowAssignment, User $author,
                              User $awardee, ?DateTime $awardedAt) {
    $this->points = $points;
    $this->shadowAssignment = $shadowAssignment;
    $this->note = $note;
    $this->author = $author;
    $this->awardee = $awardee;
    $this->createdAt = new DateTime();
    $this->updatedAt = new DateTime();
    $this->awardedAt = $awardedAt;
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
  protected $points;

  /**
   * @ORM\Column(type="text")
   */
  protected $note;

  /**
   * @var ShadowAssignment
   * @ORM\ManyToOne(targetEntity="ShadowAssignment")
   */
  protected $shadowAssignment;

  public function getShadowAssignment(): ?ShadowAssignment {
    return $this->shadowAssignment->isDeleted() ? null : $this->shadowAssignment;
  }

  /**
   * @ORM\ManyToOne(targetEntity="User")
   * Author is the person (typically teacher) who authorized the points.
   */
  protected $author;

  public function getAuthor(): ?User {
    return $this->author->isDeleted() ? null : $this->author;
  }

  /**
   * @ORM\ManyToOne(targetEntity="User")
   * Awardee is the person (typically student) who accepted (benefit from) the points.
   */
  protected $awardee;

  public function getAwardee(): ?User {
    return $this->awardee->isDeleted() ? null : $this->awardee;
  }

  /**
   * @ORM\Column(type="datetime")
   */
  protected $createdAt;

  /**
   * @ORM\Column(type="datetime", nullable=true)
   */
  protected $awardedAt;

}
