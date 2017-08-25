<?php

namespace App\Helpers\ExerciseConfig;


use App\Exceptions\ExerciseConfigException;
use JsonSerializable;
use Symfony\Component\Yaml\Yaml;
use Nette\Utils\Strings;

/**
 * Variable class which holds identifier of variable, type information and
 * actual value.
 */
class Variable implements JsonSerializable
{
  public static $REFERENCE_KEY = '$';
  public static $ESCAPE_CHAR = '\\';

  /**
   * Meta information about variable.
   * @var VariableMeta
   */
  protected $meta;

  /**
   * Determines if variable is array or not.
   * @var bool
   */
  protected $isArray;


  /**
   * Variable constructor.
   * @param VariableMeta $meta
   * @throws ExerciseConfigException
   */
  public function __construct(VariableMeta $meta) {
    $this->meta = $meta;
    $this->validateType();
    $this->validateValue();
  }

  /**
   * Validate type given during construction and set appropriate attributes.
   * @throws ExerciseConfigException
   */
  private function validateType() {
    if (strtolower($this->meta->getType()) === strtolower(VariableTypes::$FILE_ARRAY_TYPE)) {
      $this->meta->setType(VariableTypes::$FILE_ARRAY_TYPE);
      $this->isArray = true;
    } else if (strtolower($this->meta->getType()) === strtolower(VariableTypes::$FILE_TYPE)) {
      $this->meta->setType(VariableTypes::$FILE_TYPE);
      $this->isArray = false;
    } else if (strtolower($this->meta->getType()) === strtolower(VariableTypes::$STRING_ARRAY_TYPE)) {
      $this->meta->setType(VariableTypes::$STRING_ARRAY_TYPE);
      $this->isArray = true;
    } else if (strtolower($this->meta->getType()) === strtolower(VariableTypes::$STRING_TYPE)) {
      $this->meta->setType(VariableTypes::$STRING_TYPE);
      $this->isArray = false;
    } else {
      throw new ExerciseConfigException("Unknown type: {$this->meta->getType()}");
    }
  }

  /**
   * Validate variable value against variable type.
   * @throws ExerciseConfigException
   */
  private function validateValue() {
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
   * Get type of this variable.
   * @return null|string
   */
  public function getType(): ?string {
    return $this->meta->getType();
  }

  /**
   * Return true if variable can be interpreted as array.
   * @return bool
   */
  public function isArray(): bool {
    return $this->isArray;
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
