<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Kdyby\Doctrine\MagicAccessors\MagicAccessors;

/**
 * @ORM\Entity
 *
 * @method string getId()
 * @method string getNote()
 * @method ShadowAssignment getShadowAssignment()
 * @method User getAuthor()
 * @method int getPoints()
 * @method setPoints(int $points)
 */
class ShadowAssignmentEvaluation
{
  use MagicAccessors;

  public function __construct(int $points, string $note, ShadowAssignment $shadowAssignment, User $author) {
    $this->points = $points;
    $this->shadowAssignment = $shadowAssignment;
    $this->note = $note;
    $this->author = $author;
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

}
