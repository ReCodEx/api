<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\ExerciseConfig\VariableTypes;
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
    Assert::equal(VariableTypes::$STRING_TYPE, $this->loader->loadVariable(["name" => "varName", "type" => "string", "value" => "val"])->getType());
    Assert::equal(VariableTypes::$STRING_TYPE, $this->loader->loadVariable(["name" => "varName", "type" => "StRiNg", "value" => "val"])->getType());

    Assert::equal(VariableTypes::$STRING_ARRAY_TYPE, $this->loader->loadVariable(["name" => "varName", "type" => "string[]", "value" => ["val"]])->getType());
    Assert::equal(VariableTypes::$STRING_ARRAY_TYPE, $this->loader->loadVariable(["name" => "varName", "type" => "StRiNg[]", "value" => ["val"]])->getType());

    Assert::equal(VariableTypes::$FILE_TYPE, $this->loader->loadVariable(["name" => "varName", "type" => "file", "value" => "val"])->getType());
    Assert::equal(VariableTypes::$FILE_TYPE, $this->loader->loadVariable(["name" => "varName", "type" => "FiLe", "value" => "val"])->getType());

    Assert::equal(VariableTypes::$FILE_ARRAY_TYPE, $this->loader->loadVariable(["name" => "varName", "type" => "file[]", "value" => ["val"]])->getType());
    Assert::equal(VariableTypes::$FILE_ARRAY_TYPE, $this->loader->loadVariable(["name" => "varName", "type" => "FiLe[]", "value" => ["val"]])->getType());
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
    Assert::noError(function () {
      $this->loader->loadVariable([
        "name" => "varName",
        "type" => "string"
      ]);
    });
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
    $variable = new Variable("file");

    Assert::equal("file", $variable->getType());
    Assert::equal("", $variable->getValue());

    $variable->setValue("value");
    Assert::equal("value", $variable->getValue());
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
    $variable->setTestName("nice_prefix");
    Assert::equal("string[]", $variable->getType());
    Assert::true(is_array($variable->getValue()));
    Assert::equal("hehe", $variable->getValue()[0]);
    Assert::equal("haha", $variable->getValue()[1]);
    Assert::equal(["hehe", "haha"], $variable->getValueAsArray());
    Assert::equal("nice_prefix/hehe", $variable->getTestPrefixedValue()[0]);
    Assert::equal("nice_prefix/haha", $variable->getTestPrefixedValue()[1]);
    Assert::equal(["shame-shame-shame-hehe", "shame-shame-shame-haha"], $variable->getValue("shame-shame-shame-"));
  }

  public function testCorrect() {
    $variable = $this->loader->loadVariable(self::$config);
    $variable->setTestName("nice_prefix");
    Assert::equal("string", $variable->getType());
    Assert::equal("varValue", $variable->getValue());
    Assert::equal(["varValue"], $variable->getValueAsArray());
    Assert::equal("nice_prefix/varValue", $variable->getTestPrefixedValue());
    Assert::equal("shame-shame-shame-varValue", $variable->getValue("shame-shame-shame-"));
  }

}

# Testing methods run
$testCase = new TestVariable;
$testCase->run();
