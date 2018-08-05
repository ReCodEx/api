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
 * @method ShadowAssignment getShadowAssignment()
 * @method User getAuthor()
 * @method User getAwardee()
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

  /**
   * @ORM\ManyToOne(targetEntity="User")
   */
  protected $author;

  /**
   * @ORM\ManyToOne(targetEntity="User")
   */
  protected $awardee;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $createdAt;

  /**
   * @ORM\Column(type="datetime", nullable=true)
   */
  protected $awardedAt;

}
