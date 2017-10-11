<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use DateTime;

/**
 * @ORM\Entity
 * @method string getId()
 * @method string getLocale()
 */
class LocalizedText implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  public function __construct(
    string $text,
    string $locale,
    ?string $shortText = NULL,
    $createdFrom = NULL
  ) {
    $this->text = $text;
    $this->shortText = $shortText;
    $this->locale = $locale;
    $this->createdFrom = $createdFrom;
    $this->createdAt = new DateTime;
  }

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * Created from.
   * @ORM\ManyToOne(targetEntity="LocalizedText")
   * @var LocalizedText
   */
  protected $createdFrom;

  /**
   * @ORM\Column(type="string")
   */
  protected $locale;

  /**
   * @ORM\Column(type="string", nullable=TRUE)
   */
  protected $shortText;

  /**
   * @ORM\Column(type="text")
   */
  protected $text;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $createdAt;

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "locale" => $this->locale,
      "shortText" => $this->shortText,
      "text" => $this->text,
      "createdAt" => $this->createdAt->getTimestamp(),
      "createdFrom" => $this->createdFrom ? $this->createdFrom->getId() : ""
    ];
  }
}
