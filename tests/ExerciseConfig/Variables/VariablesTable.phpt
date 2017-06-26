<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Pipeline;
use App\Helpers\ExerciseConfig\Test;
use App\Helpers\ExerciseConfig\Variable;
use App\Helpers\ExerciseConfig\VariablesTable;
use Symfony\Component\Yaml\Yaml;
use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;

class TestVariablesTable extends Tester\TestCase
{
  static $config = [
    "environment" => [ "type" => "file", "value" => "envVar" ],
    "tnemnorivne" => [ "type" => "string", "value" => "vneVar" ]
  ];

  /** @var Loader */
  private $loader;

  public function __construct() {
    $this->loader = new Loader;
  }

  public function testSerialization() {
    $deserialized = Yaml::parse((string)$this->loader->loadVariablesTable(self::$config));
    Assert::equal(self::$config, $deserialized);
  }

  public function testIncorrectData() {
    Assert::exception(function () {
      $this->loader->loadVariablesTable(null);
    }, ExerciseConfigException::class);

    Assert::exception(function () {
      $this->loader->loadVariablesTable("hello");
    }, ExerciseConfigException::class);
  }

  public function testEmptyTable() {
    $table = $this->loader->loadVariablesTable([]);
    Assert::equal(0, $table->size());
  }

  public function testVariablesOperations() {
    $table = new VariablesTable;
    $variable = new Variable;

    $table->set("varA", $variable);
    Assert::equal(1, $table->size());

    $table->remove("non-existant");
    Assert::equal(1, $table->size());

    $table->remove("varA");
    Assert::equal(0, $table->size());
  }

  public function testCorrect() {
    $table = $this->loader->loadVariablesTable(self::$config);
    Assert::equal(2, $table->size());

    Assert::type(Variable::class, $table->get("environment"));
    Assert::type(Variable::class, $table->get("tnemnorivne"));

    Assert::equal("file", $table->get("environment")->getType());
    Assert::equal("string", $table->get("tnemnorivne")->getType());

    Assert::equal("envVar", $table->get("environment")->getValue());
    Assert::equal("vneVar", $table->get("tnemnorivne")->getValue());
  }

}

# Testing methods run
$testCase = new TestVariablesTable;
$testCase->run();
