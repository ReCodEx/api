<?php
namespace App\Model\Entity;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Kdyby\Doctrine\MagicAccessors\MagicAccessors;

/**
 * @ORM\Entity
 * @method int getYear();
 * @method int getTerm();
 * @method DateTime|null getBeginning()
 * @method void setBeginning(DateTime $beginning)
 * @method DateTime|null getEnd()
 * @method void setEnd(DateTime $end)
 * @method DateTime|null getAdvertiseUntil()
 * @method void setAdvertiseUntil(DateTime|null $advertiseUntil)
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

  /**
   * @ORM\Column(type="datetime", nullable=true)
   */
  protected $beginning;

  /**
   * @ORM\Column(type="datetime", nullable=true)
   */
  protected $end;

  /**
   * @ORM\Column(type="datetime", nullable=true)
   */
  protected $advertiseUntil;

  public function __construct($year, $term) {
    $this->year = $year;
    $this->term = $term;
  }

  public function isComplete(): bool {
    return $this->beginning !== null && $this->end !== null;
  }

  /**
   * Should courses in the term be advertised to the students by ReCodEx clients?
   * @param DateTime $now
   * @return bool
   */
  public function isAdvertised(DateTime $now): bool {
    if (!$this->isComplete()) {
      return false;
    }

    $advertiseUntil = $this->advertiseUntil;

    if ($advertiseUntil === null) {
      $advertiseUntil = clone $this->beginning;
      $advertiseUntil->modify("+30 days");
    }

    return $now > $this->beginning && $now < $this->end && $now < $advertiseUntil;
  }

  function jsonSerialize() {
    return [
      'id' => $this->id,
      'year' => $this->year,
      'term' => $this->term,
      'beginning' => $this->beginning ? $this->beginning->getTimestamp() : null,
      'end' => $this->end ? $this->end->getTimestamp(): null,
      'advertiseUntil' => $this->advertiseUntil ? $this->advertiseUntil->getTimestamp() : null
    ];
  }
}
