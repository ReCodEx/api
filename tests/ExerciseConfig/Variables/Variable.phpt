<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\FileArrayVariable;
use App\Helpers\ExerciseConfig\FileVariable;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Helpers\ExerciseConfig\StringArrayVariable;
use App\Helpers\ExerciseConfig\StringVariable;
use App\Helpers\ExerciseConfig\VariableMeta;
use Symfony\Component\Yaml\Yaml;
use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;

class TestVariable extends Tester\TestCase
{
  static $config = [
    "name" => "varName",
    "type" => "string",
    "value" => "varValue"
  ];

  static $configArray = [
    "name" => "varName",
    "type" => "string[]",
    "value" => [
      "hehe",
      "haha"
    ]
  ];

  /** @var Loader */
  private $loader;

  public function __construct() {
    $this->loader = new Loader(new BoxService());
  }

  public function testIncorrectData() {
    Assert::exception(function () {
      $this->loader->loadVariable(null);
    }, ExerciseConfigException::class);

    Assert::exception(function () {
      $this->loader->loadVariable([]);
    }, ExerciseConfigException::class);

    Assert::exception(function () {
      $this->loader->loadVariable("hello");
    }, ExerciseConfigException::class);
  }

  public function testIncorrectTypes() {
    Assert::exception(function () {
      $this->loader->loadVariable(["type" => "strings", "value" => "varValue"]);
    }, ExerciseConfigException::class);

    Assert::exception(function () {
      $this->loader->loadVariable(["type" => "files", "value" => "varValue"]);
    }, ExerciseConfigException::class);

    Assert::exception(function () {
      $this->loader->loadVariable(["type" => "[]string", "value" => "varValue"]);
    }, ExerciseConfigException::class);

    Assert::exception(function () {
      $this->loader->loadVariable(["type" => "[]file", "value" => "varValue"]);
    }, ExerciseConfigException::class);
  }

  public function testCorrectTypes() {
    Assert::type(StringVariable::class, $this->loader->loadVariable(["name" => "varName", "type" => "string", "value" => "val"]));
    Assert::type(StringVariable::class, $this->loader->loadVariable(["name" => "varName", "type" => "StRiNg", "value" => "val"]));

    Assert::type(StringArrayVariable::class, $this->loader->loadVariable(["name" => "varName", "type" => "string[]", "value" => ["val"]]));
    Assert::type(StringArrayVariable::class, $this->loader->loadVariable(["name" => "varName", "type" => "StRiNg[]", "value" => ["val"]]));

    Assert::type(FileVariable::class, $this->loader->loadVariable(["name" => "varName", "type" => "file", "value" => "val"]));
    Assert::type(FileVariable::class, $this->loader->loadVariable(["name" => "varName", "type" => "FiLe", "value" => "val"]));

    Assert::type(FileArrayVariable::class, $this->loader->loadVariable(["name" => "varName", "type" => "file[]", "value" => ["val"]]));
    Assert::type(FileArrayVariable::class, $this->loader->loadVariable(["name" => "varName", "type" => "FiLe[]", "value" => ["val"]]));
  }

  public function testMissingName() {
    Assert::exception(function () {
      $this->loader->loadVariable([
        "value" => "hello",
        "type" => "string"
      ]);
    }, ExerciseConfigException::class);
  }

  public function testMissingType() {
    Assert::exception(function () {
      $this->loader->loadVariable([
        "name" => "varName",
        "value" => "hello"
      ]);
    }, ExerciseConfigException::class);
  }

  public function testMissingValue() {
    Assert::exception(function () {
      $this->loader->loadVariable([
        "name" => "varName",
        "type" => "string"
      ]);
    }, ExerciseConfigException::class);
  }

  public function testEscaping() {
    $variable = $this->loader->loadVariable([
      "name" => "varName",
      "type" => "string",
      "value" => '\$reference'
    ]);

    Assert::false($variable->isReference());
    Assert::equal('\$reference', $variable->getReference());
    Assert::equal('$reference', $variable->getValue());
  }

  public function testReferences() {
    $variable = $this->loader->loadVariable([
      "name" => "varName",
      "type" => "string",
      "value" => '$reference'
    ]);

    Assert::true($variable->isReference());
    Assert::equal('reference', $variable->getReference());
    Assert::equal('$reference', $variable->getValue());
  }

  public function testSerialization() {
    $deserialized = Yaml::parse((string)$this->loader->loadVariable(self::$config));
    Assert::equal(self::$config, $deserialized);
  }

  public function testVariablesOperations() {
    $variable = new VariableMeta;

    Assert::equal(null, $variable->getType());
    Assert::equal(null, $variable->getValue());

    $variable->setType("file");
    $variable->setValue("value");

    Assert::equal("file", $variable->getType());
    Assert::equal("value", $variable->getValue());
  }

  public function testBadArrayType() {
    Assert::exception(function () {
      $this->loader->loadVariable([
        "name" => "varName",
        "type" => "string[]",
        "value" => "text"
      ]);
    }, ExerciseConfigException::class);
  }

  public function testBadScalarType() {
    Assert::exception(function () {
      $this->loader->loadVariable([
        "name" => "varName",
        "type" => "string",
        "value" => [
          "haha",
          "hehe"
        ]
      ]);
    }, ExerciseConfigException::class);
  }

  public function testCorrectArray() {
    $variable = $this->loader->loadVariable(self::$configArray);
    Assert::equal("string[]", $variable->getType());
    Assert::true(is_array($variable->getValue()));
    Assert::equal("hehe", $variable->getValue()[0]);
    Assert::equal("haha", $variable->getValue()[1]);
  }

  public function testCorrect() {
    $variable = $this->loader->loadVariable(self::$config);
    Assert::equal("string", $variable->getType());
    Assert::equal("varValue", $variable->getValue());
  }

}

# Testing methods run
$testCase = new TestVariable;
$testCase->run();
