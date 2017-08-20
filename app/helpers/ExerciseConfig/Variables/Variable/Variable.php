<?php

namespace App\Helpers\ExerciseConfig;


use JsonSerializable;
use Symfony\Component\Yaml\Yaml;
use Nette\Utils\Strings;

/**
 * Base class for variables.
 */
abstract class Variable implements JsonSerializable
{
  public static $REFERENCE_KEY = "$";
  public static $ESCAPE_CHAR = "\\";

  /**
   * Meta information about variable.
   * @var VariableMeta
   */
  protected $meta;


  /**
   * Variable constructor.
   * @param VariableMeta $meta
   */
  public function __construct(VariableMeta $meta) {
    $this->meta = $meta;
  }


  /**
   * Get type of this variable.
   * @return null|string
   */
  public abstract function getType(): ?string;

  /**
   * Get name of the variable.
   * @return null|string
   */
  public function getName(): ?string {
    return $this->meta->getName();
  }

  /**
   * Get value of the variable.
   * @return null|string
   */
  public function getValue(): ?string {
    $val = $this->meta->getValue();
    if (Strings::startsWith(self::$ESCAPE_CHAR . self::$REFERENCE_KEY, $val)) {
      return Strings::substring($val, 2);
    }

    return $val;
  }

  /**
   * Get name of the referenced variable if any.
   * @note Check if variable is reference has to precede this call.
   * @return null|string
   */
  public function getReference(): ?string {
    $val = $this->meta->getValue();
    if (Strings::startsWith(self::$REFERENCE_KEY, $val)) {
      return Strings::substring($val, 1);
    }

    return $val;
  }

  /**
   * Check if variable is reference to another variable.
   * @return bool
   */
  public function isReference(): bool {
    return Strings::startsWith(self::$REFERENCE_KEY, $this->meta->getValue());
  }


  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    return $this->meta->toArray();
  }

  /**
   * Serialize the config.
   * @return string
   */
  public function __toString(): string {
    return Yaml::dump($this->toArray());
  }

  /**
   * Enable automatic serialization to JSON
   * @return array
   */
  public function jsonSerialize() {
    return $this->toArray();
  }
}
