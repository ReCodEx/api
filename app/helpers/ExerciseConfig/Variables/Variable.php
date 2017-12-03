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

  /** Name of the name key */
  const NAME_KEY = "name";
  /** Name of the type key */
  const TYPE_KEY = "type";
  /** Name of the value key */
  const VALUE_KEY = "value";

  /**
   * Variable name.
   * @var string
   */
  protected $name = null;

  /**
   * Variable type.
   * @var string
   */
  protected $type = null;

  /**
   * Variable value.
   * @var string|array
   */
  protected $value = null;

  /**
   * Prefix of variable.
   * @var string
   */
  protected $prefix = "";

  /**
   * Determines if variable is array or not.
   * @var bool
   */
  protected $isArray = false;


  /**
   * Variable constructor.
   * @param string $type
   * @param string|null $name
   * @param string|array $value
   * @throws ExerciseConfigException
   */
  public function __construct(string $type, string $name = null, $value = null) {
    $this->type = $type;
    $this->name = $name;
    $this->value = $value;
    $this->validateType();
    $this->validateValue();
  }

  /**
   * Validate type given during construction and set appropriate attributes.
   * @throws ExerciseConfigException
   */
  private function validateType() {
    if (strtolower($this->type) === strtolower(VariableTypes::$FILE_ARRAY_TYPE)) {
      $this->type = VariableTypes::$FILE_ARRAY_TYPE;
      $this->isArray = true;
    } else if (strtolower($this->type) === strtolower(VariableTypes::$FILE_TYPE)) {
      $this->type = VariableTypes::$FILE_TYPE;
    } else if (strtolower($this->type) === strtolower(VariableTypes::$REMOTE_FILE_ARRAY_TYPE)) {
      $this->type = VariableTypes::$REMOTE_FILE_ARRAY_TYPE;
      $this->isArray = true;
    } else if (strtolower($this->type) === strtolower(VariableTypes::$REMOTE_FILE_TYPE)) {
      $this->type = VariableTypes::$REMOTE_FILE_TYPE;
    } else if (strtolower($this->type) === strtolower(VariableTypes::$STRING_ARRAY_TYPE)) {
      $this->type = VariableTypes::$STRING_ARRAY_TYPE;
      $this->isArray = true;
    } else if (strtolower($this->type) === strtolower(VariableTypes::$STRING_TYPE)) {
      $this->type = VariableTypes::$STRING_TYPE;
    } else {
      throw new ExerciseConfigException("Unknown type: {$this->type}");
    }
  }

  /**
   * Validate variable value against variable type.
   * @throws ExerciseConfigException
   */
  private function validateValue() {
    if ($this->value === null) {
      // value is null this means that default value should be assigned
      if ($this->isArray()) {
        $this->value = [];
      } else {
        $this->value = "";
      }
    }

    if ($this->isReference()) {
      // if variable is reference, then it always contains string and
      // does not have to be validated
      return;
    } else if ($this->isArray()) {
      // noop, array can be defined with regexp
    } else {
      if (!is_scalar($this->value)) {
        throw new ExerciseConfigException("Variable '{$this->name}' should be scalar");
      }
    }
  }


  /**
   * Get name of this variable.
   * @return null|string
   */
  public function getName(): ?string {
    return $this->name;
  }

  /**
   * Set name of this variable.
   * @param string $name
   * @return Variable
   */
  public function setName(string $name): Variable {
    $this->name = $name;
    return $this;
  }

  /**
   * Get type of this variable.
   * @return null|string
   */
  public function getType(): ?string {
    return $this->type;
  }

  /**
   * Check if variable is empty.
   * @return bool
   */
  public function isEmpty(): bool {
    return empty($this->value);
  }

  /**
   * Return true if variable type is an array.
   * @return bool
   */
  public function isArray(): bool {
    return $this->isArray;
  }

  /**
   * Return true if variable value is array.
   * @return bool
   */
  public function isValueArray(): bool {
    return is_array($this->value);
  }

  /**
   * Return true if variable is of file type. Remote file type is not involved
   * here and returns false.
   * @return bool
   */
  public function isFile(): bool {
    return $this->type === VariableTypes::$FILE_TYPE ||
      $this->type === VariableTypes::$FILE_ARRAY_TYPE;
  }

  /**
   * Return true if variable is of remote file type.
   * @return bool
   */
  public function isRemoteFile(): bool {
    return $this->type === VariableTypes::$REMOTE_FILE_TYPE ||
      $this->type === VariableTypes::$REMOTE_FILE_ARRAY_TYPE;
  }

  /**
   * Get prefixed value or values.
   * This method should be used in boxes compilation.
   * @param string $prefix another prefix which can be added to values
   * @return array|string
   */
  public function getPrefixedValue(string $prefix = "") {
    $value = $this->getValue();
    $prefix = $prefix . $this->prefix;
    if (is_scalar($value)) {
      return $prefix . $value;
    } else {
      return array_map(function ($val) use ($prefix) {
        return $prefix . $val;
      }, $value);
    }
  }

  /**
   * Get prefixed value as array if it is not array already.
   * This method should be used in boxes compilation.
   * @param string $prefix another prefix which can be added to values
   * @return array|string
   */
  public function getPrefixedValueAsArray(string $prefix = "") {
    $value = $this->getPrefixedValue($prefix);
    if (!is_array($value)) {
      $value = [$value];
    }
    return $value;
  }

  /**
   * Get value of the variable.
   * @return array|string
   */
  public function getValue() {
    $value = $this->value;
    if (is_scalar($value) && Strings::startsWith($value, self::$ESCAPE_CHAR . self::$REFERENCE_KEY)) {
      return Strings::substring($value, 1);
    }

    return $value;
  }

  /**
   * Get value of the variable as array if it is not an array already.
   * @return array|string
   */
  public function getValueAsArray() {
    $value = $this->getValue();
    if (!is_array($value)) {
      $value = [$value];
    }
    return $value;
  }

  /**
   * Set value of this variable.
   * @param array|string $value
   * @return Variable
   * @throws ExerciseConfigException
   */
  public function setValue($value): Variable {
    $this->value = $value;
    $this->validateValue();
    return $this;
  }

  /**
   * Set given prefix to variable.
   * @param string $prefix
   * @return Variable
   */
  public function setValuePrefix(string $prefix): Variable {
    $this->prefix = $prefix;
    return $this;
  }

  /**
   * Get current variable prefix.
   * @return string
   */
  public function getValuePrefix(): string {
    return $this->prefix;
  }

  /**
   * Get name of the referenced variable if any.
   * @note Check if variable is reference has to precede this call.
   * @return null|string
   */
  public function getReference(): ?string {
    $val = $this->value;
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
    $val = $this->value;
    return is_scalar($val) && Strings::startsWith($val, self::$REFERENCE_KEY);
  }


  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    $data = [];

    $data[self::NAME_KEY] = $this->name;
    $data[self::TYPE_KEY] = $this->type;
    if ($this->value !== null) { $data[self::VALUE_KEY] = $this->value; }

    return $data;
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
