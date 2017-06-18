<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Pipeline;
use App\Helpers\ExerciseConfig\Variable;
use Symfony\Component\Yaml\Yaml;
use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;

class TestPipeline extends Tester\TestCase
{
  static $config = [
    "variables" => [
      "varA" => [ "type" => "string", "value" => "valA" ],
      "varB" => [ "type" => "file", "value" => "valB" ]
    ]
  ];

  /** @var Loader */
  private $loader;

  public function __construct() {
    $this->loader = new Loader;
  }

  public function testIncorrectData() {
    Assert::exception(function () {
      $this->loader->loadPipeline(null);
    }, ExerciseConfigException::class);

    Assert::exception(function () {
      $this->loader->loadPipeline("hello");
    }, ExerciseConfigException::class);
  }

  public function testSerialization() {
    $deserialized = Yaml::parse((string)$this->loader->loadPipeline(self::$config));
    Assert::equal(self::$config, $deserialized);
  }

  public function testVariablesOperations() {
    $pipeline = new Pipeline;

    $variable = new Variable;
    $pipeline->addVariable("variableA", $variable);
    Assert::type(Variable::class, $pipeline->getVariable("variableA"));

    $pipeline->removeVariable("non-existant");
    Assert::count(1, $pipeline->getVariables());

    $pipeline->removeVariable("variableA");
    Assert::count(0, $pipeline->getVariables());
  }

  public function testCorrect() {
    $pipeline = $this->loader->loadPipeline(self::$config);
    Assert::count(2, $pipeline->getVariables());

    Assert::equal("string", $pipeline->getVariable("varA")->getType());
    Assert::equal("file", $pipeline->getVariable("varB")->getType());
    Assert::equal("valA", $pipeline->getVariable("varA")->getValue());
    Assert::equal("valB", $pipeline->getVariable("varB")->getValue());
  }

}

# Testing methods run
$testCase = new TestPipeline;
$testCase->run();
