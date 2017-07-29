<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Pipeline;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxMeta;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Helpers\ExerciseConfig\Pipeline\Box\DataInBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\JudgeNormalBox;
use App\Helpers\ExerciseConfig\StringVariable;
use Symfony\Component\Yaml\Yaml;
use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;

class TestPipeline extends Tester\TestCase
{
  static $config = [
    "variables" => [
      [
        "name" => "varA",
        "type" => "string",
        "value" => "valA"
      ]
    ],
    "boxes" => [
      [
        "name" => "file",
        "type" => "data-in",
        "portsIn" => [],
        "portsOut" => [ "in_data" => ['type' => 'file', 'value' => "out_data_file"] ]
      ],
      [
        "name" => "evaluation",
        "type" => "judge-normal",
        "portsIn" => [
          "expected_output" => ['type' => 'file', 'value' => "test_in_file"],
          "actual_output" => ['type' => 'file', 'value' => "out_exec_file"]
        ],
        "portsOut" => [ "score" => ['type' => 'string', 'value' => "judge_score"] ]
      ]
    ]
  ];


  /** @var Loader */
  private $loader;

  public function __construct() {
    $this->loader = new Loader(new BoxService());
  }

  public function testSerialization() {
    $deserialized = Yaml::parse((string)$this->loader->loadPipeline(self::$config));
    Assert::equal(self::$config, $deserialized);
  }

  public function testIncorrectData() {
    Assert::exception(function () {
      $this->loader->loadPipeline(null);
    }, ExerciseConfigException::class);

    Assert::exception(function () {
      $this->loader->loadPipeline("hello");
    }, ExerciseConfigException::class);
  }

  public function testEmptyPipeline() {
    $pipeline = $this->loader->loadPipeline([
      "variables" => [],
      "boxes" => []
    ]);
    Assert::equal(0, $pipeline->getVariablesTable()->size());
    Assert::equal(0, $pipeline->size());
  }

  public function testBoxesOperations() {
    $boxMeta = new BoxMeta;
    $boxMeta->setName("boxA");

    $pipeline = new Pipeline;
    $box = new DataInBox($boxMeta);

    $pipeline->set($box);
    Assert::equal(1, $pipeline->size());

    $pipeline->remove("non-existant");
    Assert::equal(1, $pipeline->size());

    $pipeline->remove("boxA");
    Assert::equal(0, $pipeline->size());
  }

  public function testCorrect() {
    $pipeline = $this->loader->loadPipeline(self::$config);
    Assert::equal(1, $pipeline->getVariablesTable()->size());
    Assert::equal(2, $pipeline->size());

    Assert::type(StringVariable::class, $pipeline->getVariablesTable()->get("varA"));
    Assert::equal("valA", $pipeline->getVariablesTable()->get("varA")->getValue());

    Assert::type(DataInBox::class, $pipeline->get("file"));
    Assert::type(JudgeNormalBox::class, $pipeline->get("evaluation"));

    Assert::equal("file", $pipeline->get("file")->getName());
    Assert::equal("evaluation", $pipeline->get("evaluation")->getName());

    Assert::count(0, $pipeline->get("file")->getInputPorts());
    Assert::count(2, $pipeline->get("evaluation")->getInputPorts());

    Assert::count(1, $pipeline->get("file")->getOutputPorts());
    Assert::count(1, $pipeline->get("evaluation")->getOutputPorts());
  }

}

# Testing methods run
$testCase = new TestPipeline;
$testCase->run();
