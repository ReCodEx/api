<?php

namespace App\Helpers\ExerciseConfig;


use App\Exceptions\ExerciseConfigException;
use JsonSerializable;
use Symfony\Component\Yaml\Yaml;
use Nette\Utils\Strings;

/**
 * Base class for variables.
 */
abstract class Variable implements JsonSerializable
{
  public static $REFERENCE_KEY = '$';
  public static $ESCAPE_CHAR = '\\';

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
    $this->validate();
  }


  /**
   * Get type of this variable.
   * @return null|string
   */
  public abstract function getType(): ?string;

  /**
   * Return true if variable can be interpreted as array.
   * @return bool
   */
  public abstract function isArray(): bool;


  /**
   * Validate variable value against variable type.
   * @throws ExerciseConfigException
   */
  private function validate() {
    if ($this->isReference()) {
      // if variable is reference, then it always contains string and
      // does not have to be validated
      return;
    } else if ($this->isArray()) {
      if (!is_array($this->meta->getValue())) {
        throw new ExerciseConfigException("Variable '{$this->meta->getName()}' should be array");
      }
    } else {
      if (!is_scalar($this->meta->getValue())) {
        throw new ExerciseConfigException("Variable '{$this->meta->getName()}' should be scalar");
      }
    }
  }

  /**
   * Get name of the variable.
   * @return null|string
   */
  public function getName(): ?string {
    return $this->meta->getName();
  }

  /**
   * Get value of the variable.
   * @return array|string
   */
  public function getValue() {
    $val = $this->meta->getValue();
    if (is_scalar($val) && Strings::startsWith($val, self::$ESCAPE_CHAR . self::$REFERENCE_KEY)) {
      return Strings::substring($val, 1);
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
    if (is_scalar($val) && Strings::startsWith($val, self::$REFERENCE_KEY)) {
      return Strings::substring($val, 1);
    }

    return $val;
  }

  /**
   * Check if variable is reference to another variable.
   * @return bool
   */
  public function isReference(): bool {
    $val = $this->meta->getValue();
    return is_scalar($val) && Strings::startsWith($val, self::$REFERENCE_KEY);
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
