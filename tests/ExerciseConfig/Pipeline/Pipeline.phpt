<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Pipeline;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxMeta;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Helpers\ExerciseConfig\Pipeline\Box\FileInBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\JudgeBox;
use App\Helpers\ExerciseConfig\VariableTypes;
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
        "type" => "file-in",
        "portsIn" => [],
        "portsOut" => [ "input" => ['type' => 'file', 'value' => "out_data_file"] ]
      ],
      [
        "name" => "evaluation",
        "type" => "judge",
        "portsIn" => [
          "judge-type" => ['type' => 'string', 'value' => ""],
          "args" => ['type' => 'string[]', 'value' => ""],
          "custom-judge" => ['type' => 'file', 'value' => ""],
          "expected-output" => ['type' => 'file', 'value' => "test_in_file"],
          "actual-output" => ['type' => 'file', 'value' => "out_exec_file"]
        ],
        "portsOut" => []
      ],
      [
        "name" => "file-out",
        "type" => "file-out",
        "portsIn" => [ "output" => ['type' => 'file', 'value' => "out_data_file"] ],
        "portsOut" => []
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
    $box = new FileInBox($boxMeta);

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
    Assert::equal(3, $pipeline->size());

    Assert::equal(VariableTypes::$STRING_TYPE, $pipeline->getVariablesTable()->get("varA")->getType());
    Assert::equal("valA", $pipeline->getVariablesTable()->get("varA")->getValue());

    Assert::type(FileInBox::class, $pipeline->get("file"));
    Assert::type(JudgeBox::class, $pipeline->get("evaluation"));

    Assert::equal("file", $pipeline->get("file")->getName());
    Assert::equal("evaluation", $pipeline->get("evaluation")->getName());

    Assert::count(0, $pipeline->get("file")->getInputPorts());
    Assert::count(5, $pipeline->get("evaluation")->getInputPorts());

    Assert::count(1, $pipeline->get("file")->getOutputPorts());

    // check in and out data boxes
    Assert::count(1, $pipeline->getDataInBoxes());
    Assert::count(1, $pipeline->getDataOutBoxes());
    Assert::count(1, $pipeline->getOtherBoxes());

    Assert::true(array_key_exists("file", $pipeline->getDataInBoxes()));
    Assert::true(array_key_exists("file-out", $pipeline->getDataOutBoxes()));
    Assert::true(array_key_exists("evaluation", $pipeline->getOtherBoxes()));
  }

}

# Testing methods run
$testCase = new TestPipeline;
$testCase->run();
