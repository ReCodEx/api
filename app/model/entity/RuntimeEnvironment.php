<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

/**
 * @ORM\Entity
 */
class RuntimeEnvironment implements JsonSerializable
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="string")
   */
  protected $name;

  /**
   * @ORM\Column(type="string")
   */
  protected $language;

  /**
   * List of extensions in YAML format. No extension is also extension.
   * Example: [ "cpp", "hpp", "h", "" ]
   * @ORM\Column(type="string")
   */
  protected $extensions;

  /**
   * @ORM\Column(type="string")
   */
  protected $platform;

  /**
   * @ORM\Column(type="text")
   */
  protected $description;


  public function __construct(
    $name,
    $language,
    $extensions,
    $platform,
    $description
  ) {
    $this->name = $name;
    $this->language = $language;
    $this->extensions = $extensions;
    $this->platform = $platform;
    $this->description = $description;
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "name" => $this->name,
      "language" => $this->language,
      "extensions" => $this->extensions,
      "platform" => $this->platform,
      "description" => $this->description
    ];
  }

}
