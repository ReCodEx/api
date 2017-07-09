<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\FileArrayVariable;
use App\Helpers\ExerciseConfig\FileVariable;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Helpers\ExerciseConfig\StringArrayVariable;
use App\Helpers\ExerciseConfig\StringVariable;
use App\Helpers\ExerciseConfig\VariableFactory;
use App\Helpers\ExerciseConfig\VariableMeta;
use Symfony\Component\Yaml\Yaml;
use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;

class TestVariable extends Tester\TestCase
{
  static $config = [
    "type" => "string",
    "value" => "varValue"
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
    Assert::type(StringVariable::class, $this->loader->loadVariable(["type" => "string", "value" => "val"]));
    Assert::type(StringVariable::class, $this->loader->loadVariable(["type" => "StRiNg", "value" => "val"]));

    Assert::type(StringArrayVariable::class, $this->loader->loadVariable(["type" => "string[]", "value" => "val"]));
    Assert::type(StringArrayVariable::class, $this->loader->loadVariable(["type" => "StRiNg[]", "value" => "val"]));

    Assert::type(FileVariable::class, $this->loader->loadVariable(["type" => "file", "value" => "val"]));
    Assert::type(FileVariable::class, $this->loader->loadVariable(["type" => "FiLe", "value" => "val"]));

    Assert::type(FileArrayVariable::class, $this->loader->loadVariable(["type" => "file[]", "value" => "val"]));
    Assert::type(FileArrayVariable::class, $this->loader->loadVariable(["type" => "FiLe[]", "value" => "val"]));
  }

  public function testMissingType() {
    Assert::exception(function () {
      $this->loader->loadVariable([
        "value" => "hello"
      ]);
    }, ExerciseConfigException::class);
  }

  public function testMissingValue() {
    Assert::exception(function () {
      $this->loader->loadVariable([
        "type" => "string"
      ]);
    }, ExerciseConfigException::class);
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

  public function testCorrect() {
    $variable = $this->loader->loadVariable(self::$config);
    Assert::equal("string", $variable->getType());
    Assert::equal("varValue", $variable->getValue());
  }

}

# Testing methods run
$testCase = new TestVariable;
$testCase->run();
