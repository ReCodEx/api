<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Kdyby\Doctrine\MagicAccessors\MagicAccessors;
use DateTime;

/**
 * @ORM\Entity
 *
 * @method string getId()
 * @method string getNote()
 * @method ShadowAssignment getShadowAssignment()
 * @method User getAuthor()
 * @method User getEvaluatee()
 * @method int getPoints()
 * @method DateTime getCreatedAt()
 * @method DateTime getEvaluatedAt()
 * @method setPoints(int $points)
 * @method setNote(string $note)
 * @method void setEvaluatedAt(DateTime $evaluatedAt)
 */
class ShadowAssignmentEvaluation
{
  use MagicAccessors;
  use UpdateableEntity;

  public function __construct(int $points, string $note, ShadowAssignment $shadowAssignment, User $author,
                              User $evaluatee, DateTime $evaluatedAt) {
    $this->points = $points;
    $this->shadowAssignment = $shadowAssignment;
    $this->note = $note;
    $this->author = $author;
    $this->evaluatee = $evaluatee;
    $this->createdAt = new DateTime;
    $this->updatedAt = new DateTime;
    $this->evaluatedAt = $evaluatedAt;
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
  protected $evaluatee;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $createdAt;

  /**
   * @ORM\Column(type="datetime", nullable=true)
   */
  protected $evaluatedAt;

}
