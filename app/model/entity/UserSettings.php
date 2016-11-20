<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class UserSettings implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  public function __construct(bool $darkTheme = TRUE, bool $vimMode = FALSE, string $defaultLanguage = "en") {
    $this->darkTheme = $darkTheme;
    $this->vimMode = $vimMode;
    $this->defaultLanguage = $defaultLanguage;
  }

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $darkTheme;

  /**
   * @ORM\Column(type="boolean")
   */
  protected $vimMode;

  /**
   * @ORM\Column(type="string")
   */
  protected $defaultLanguage;

  public function jsonSerialize() {
    return [
      "darkTheme" => $this->darkTheme,
      "vimMode" => $this->vimMode,
      "defaultLanguage" => $this->defaultLanguage
    ];
  }
}
