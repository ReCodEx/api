<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
use App\Exceptions\ApiException;

/**
 * @ORM\Entity
 *
 * @method string getId()
 * @method string getName()
 * @method string getLongName()
 * @method string getExtensions()
 * @method string getPlatform()
 * @method string getDescription()
 * @method string getDefaultVariables()
 */
class RuntimeEnvironment implements JsonSerializable
{
  use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="string", length=32)
   */
  protected $id;

  /**
   * @ORM\Column(type="string")
   */
  protected $name;

  /**
   * @ORM\Column(type="string")
   */
  protected $longName;

  /**
   * List of extensions in YAML format. No extension is also extension.
   * Example: [ "cpp", "hpp", "h", "" ]
   * @ORM\Column(type="string")
   */
  protected $extensions;

  /**
   * Parse given string into yaml structure and return it.
   * @return array decoded YAML
   * @throws ApiException in case of parsing error
   */
  public function getExtensionsList() {
    try {
      $parsedConfig = Yaml::parse($this->extensions);
    } catch (ParseException $e) {
      throw new ApiException("Yaml cannot be parsed: " . $e->getMessage());
    }

    return $parsedConfig;
  }

  /**
   * @ORM\Column(type="string")
   */
  protected $platform;

  /**
   * @ORM\Column(type="text")
   */
  protected $description;

  /**
   * @ORM\Column(type="text")
   */
  protected $defaultVariables;

  /**
   * Parse variables into yaml structure and return it.
   * @return array decoded YAML
   * @throws ApiException
   */
  public function getParsedVariables(): array {
    try {
      return Yaml::parse($this->defaultVariables);
    } catch (ParseException $e) {
      throw new ApiException("Yaml cannot be parsed: " . $e->getMessage());
    }
  }


  public function __construct(
    $id,
    $name,
    $language,
    $extensions,
    $platform,
    $description,
    $defaultVariables = "[]"
  ) {
    $this->id = $id;
    $this->name = $name;
    $this->longName = $language;
    $this->extensions = $extensions;
    $this->platform = $platform;
    $this->description = $description;
    $this->defaultVariables = $defaultVariables;
  }

  public function jsonSerialize() {
    return [
      "id" => $this->id,
      "name" => $this->name,
      "longName" => $this->longName,
      "extensions" => $this->extensions,
      "platform" => $this->platform,
      "description" => $this->description,
      "defaultVariables" => $this->getParsedVariables()
    ];
  }

}
