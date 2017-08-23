<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\FileArrayVariable;
use App\Helpers\ExerciseConfig\FileVariable;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Helpers\ExerciseConfig\StringArrayVariable;
use App\Helpers\ExerciseConfig\StringVariable;
use App\Helpers\ExerciseConfig\VariableMeta;
use App\Helpers\ExerciseConfig\VariablesTable;
use Symfony\Component\Yaml\Yaml;
use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;

class TestVariablesTable extends Tester\TestCase
{
  static $config = [
    [ "name" => "environment", "type" => "file", "value" => "envVar" ],
    [ "name" => "tnemnorivne", "type" => "string", "value" => "vneVar" ],
    [ "name" => "varFileArr", "type" => "file[]", "value" => ["envFileArrVar"] ],
    [ "name" => "varStringArr", "type" => "string[]", "value" => ["envStringArrVar"] ]
  ];

  /** @var Loader */
  private $loader;

  public function __construct() {
    $this->loader = new Loader(new BoxService());
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
    $variableMeta = (new VariableMeta)->setName("varA")->setValue("valA");
    $variable = new StringVariable($variableMeta);

    $table->set($variable);
    Assert::equal(1, $table->size());

    $table->remove("non-existant");
    Assert::equal(1, $table->size());

    $table->remove("varA");
    Assert::equal(0, $table->size());
  }

  public function testCorrect() {
    $table = $this->loader->loadVariablesTable(self::$config);
    Assert::equal(4, $table->size());

    Assert::type(FileVariable::class, $table->get("environment"));
    Assert::type(StringVariable::class, $table->get("tnemnorivne"));
    Assert::type(FileArrayVariable::class, $table->get("varFileArr"));
    Assert::type(StringArrayVariable::class, $table->get("varStringArr"));

    Assert::equal("file", $table->get("environment")->getType());
    Assert::equal("string", $table->get("tnemnorivne")->getType());

    Assert::equal("envVar", $table->get("environment")->getValue());
    Assert::equal("vneVar", $table->get("tnemnorivne")->getValue());
  }

}

# Testing methods run
$testCase = new TestVariablesTable;
$testCase->run();
