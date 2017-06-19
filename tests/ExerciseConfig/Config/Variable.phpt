<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Variable;
use Symfony\Component\Yaml\Yaml;
use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;

class TestVariable extends Tester\TestCase
{
  static $config = [
    "type" => "varType",
    "value" => "varValue"
  ];

  /** @var Loader */
  private $loader;

  public function __construct() {
    $this->loader = new Loader;
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
    $variable = new Variable;

    Assert::equal(null, $variable->getType());
    Assert::equal(null, $variable->getValue());

    $variable->setType("file");
    $variable->setValue("value");

    Assert::equal("file", $variable->getType());
    Assert::equal("value", $variable->getValue());
  }

  public function testCorrect() {
    $variable = $this->loader->loadVariable(self::$config);
    Assert::equal("varType", $variable->getType());
    Assert::equal("varValue", $variable->getValue());
  }

}

# Testing methods run
$testCase = new TestVariable;
$testCase->run();
