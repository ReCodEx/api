<?php
namespace App\Model\Entity;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Kdyby\Doctrine\Entities\MagicAccessors;

/**
 * @ORM\Entity
 * @method int getYear();
 * @method int getTerm();
 */
class SisValidTerm implements JsonSerializable {
  use MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="integer")
   */
  protected $year;

  /**
   * @ORM\Column(type="integer")
   */
  protected $term;

  public function __construct($year, $term) {
    $this->year = $year;
    $this->term = $term;
  }

  function jsonSerialize() {
    return [
      'year' => $this->year,
      'term' => $this->term
    ];
  }
}