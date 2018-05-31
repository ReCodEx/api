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
 * @method int getPoints()
 * @method setPoints(int $points)
 */
class ShadowAssignmentEvaluation
{
  use MagicAccessors;

  public function __construct(string $note, ShadowAssignment $shadowAssignment) {
    $this->points = 0;
    $this->shadowAssignment = $shadowAssignment;
    $this->note = $note;
  }

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

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
   * @ORM\Column(type="integer")
   */
  protected $points;

}
