<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Helpers\ExerciseConfig\PipelineVars;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\ExerciseConfig\VariableTypes;
use Symfony\Component\Yaml\Yaml;
use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;

class TestPipelineVars extends Tester\TestCase
{
  static $config = [
    "name" => "pipeline",
    "variables" => [
      [ "name" => "varA", "type" => "string", "value" => "valA" ],
      [ "name" => "varB", "type" => "file", "value" => "valB" ]
    ]
  ];

  /** @var Loader */
  private $loader;

  public function __construct() {
    $this->loader = new Loader(new BoxService());
  }

  public function testIncorrectData() {
    Assert::exception(function () {
      $this->loader->loadPipelineVars(null);
    }, ExerciseConfigException::class);

    Assert::exception(function () {
      $this->loader->loadPipelineVars("hello");
    }, ExerciseConfigException::class);
  }

  public function testSerialization() {
    $deserialized = Yaml::parse((string)$this->loader->loadPipelineVars(self::$config));
    Assert::equal(self::$config, $deserialized);
  }

  public function testVariablesOperations() {
    $pipeline = new PipelineVars();
    $variable = (new Variable("string"))->setName("variableA")->setValue("valA");

    $pipeline->getVariablesTable()->set($variable);
    Assert::equal(VariableTypes::$STRING_TYPE, $pipeline->getVariablesTable()->get("variableA")->getType());

    $pipeline->getVariablesTable()->remove("non-existant");
    Assert::count(1, $pipeline->getVariablesTable()->getAll());

    $pipeline->getVariablesTable()->remove("variableA");
    Assert::count(0, $pipeline->getVariablesTable()->getAll());
  }

  public function testCorrect() {
    $pipeline = $this->loader->loadPipelineVars(self::$config);
    Assert::count(2, $pipeline->getVariablesTable()->getAll());

    Assert::equal("pipeline", $pipeline->getId());
    Assert::equal("string", $pipeline->getVariablesTable()->get("varA")->getType());
    Assert::equal("file", $pipeline->getVariablesTable()->get("varB")->getType());
    Assert::equal("valA", $pipeline->getVariablesTable()->get("varA")->getValue());
    Assert::equal("valB", $pipeline->getVariablesTable()->get("varB")->getValue());
  }

}

# Testing methods run
$testCase = new TestPipelineVars();
$testCase->run();
