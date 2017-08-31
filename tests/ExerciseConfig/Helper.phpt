<?php

include '../bootstrap.php';

use App\Helpers\ExerciseConfig\Helper;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Helpers\ExerciseConfig\VariablesTable;
use App\Helpers\ExerciseConfig\VariableTypes;
use Tester\Assert;


class TestExerciseConfigHelper extends Tester\TestCase
{
  /** @var Helper */
  private $helper;

  /** @var Loader */
  private $loader;

  public function __construct() {
    $this->helper = new Helper;
    $this->loader = new Loader(new BoxService());
  }


  public function testVariablesForExerciseEmptyArray() {
    $result = $this->helper->getVariablesForExercise([], new VariablesTable());
    Assert::equal([], $result);
  }

  public function testVariablesForExerciseSimple() {
    $pipelineId = "pipeline";
    $pipeline = $this->loader->loadPipeline([
      "variables" => [],
      "boxes" => [
        [
          "name" => "file",
          "type" => "data-in",
          "portsIn" => [],
          "portsOut" => ["in-data" => ['type' => 'file', 'value' => "data-in"]]
        ]
      ]
    ]);

    $result = $this->helper->getVariablesForExercise([$pipelineId => $pipeline], new VariablesTable());
    Assert::count(1, $result);
    Assert::true(array_key_exists($pipelineId, $result));
    Assert::count(1, $result[$pipelineId]);

    Assert::equal("data-in", $result[$pipelineId][0]->getName());
    Assert::equal(VariableTypes::$REMOTE_FILE_TYPE, $result[$pipelineId][0]->getType());
    Assert::equal("", $result[$pipelineId][0]->getValue());
  }

  public function testVariablesForExerciseEmptyAfterJoin() {
    $pipelineAid = "pipelineA";
    $pipelineA = $this->loader->loadPipeline([
      "variables" => [],
      "boxes" => [
        [
          "name" => "file",
          "type" => "data-out",
          "portsIn" => ["out-data" => ['type' => 'file', 'value' => "join"]],
          "portsOut" => []
        ]
      ]
    ]);
    $pipelineBid = "pipelineB";
    $pipelineB = $this->loader->loadPipeline([
      "variables" => [],
      "boxes" => [
        [
          "name" => "file",
          "type" => "data-in",
          "portsIn" => [],
          "portsOut" => [ "in-data" => ['type' => 'file', 'value' => "join"] ]
        ]
      ]
    ]);

    $result = $this->helper->getVariablesForExercise(
      [
        $pipelineAid => $pipelineA,
        $pipelineBid => $pipelineB
      ],
      new VariablesTable());
    Assert::count(2, $result);
    Assert::true(array_key_exists($pipelineAid, $result));
    Assert::true(array_key_exists($pipelineBid, $result));

    Assert::count(0, $result[$pipelineAid]);
    Assert::count(0, $result[$pipelineBid]);
  }

  public function testVariablesForExerciseReferences() {
    $pipelineAid = "pipelineA";
    $pipelineA = $this->loader->loadPipeline([
      "variables" => [
        [
          "name" => "actual",
          "type" => "file",
          "value" => '$actualFile'
        ],
        [
          "name" => "expected",
          "type" => "file",
          "value" => '$expectedFile'
        ]
      ],
      "boxes" => [
        [
          "name" => "file",
          "type" => "data-out",
          "portsIn" => ["out-data" => ['type' => 'file', 'value' => "join"]],
          "portsOut" => []
        ],
        [
          "name" => "judge",
          "type" => "judge-normal",
          "portsIn" => ["actual-output" => ['type' => 'file', 'value' => "actual"],
            "expected-output" => ['type' => 'file', 'value' => "expected"]],
          "portsOut" => []
        ]
      ]
    ]);
    $pipelineBid = "pipelineB";
    $pipelineB = $this->loader->loadPipeline([
      "variables" => [],
      "boxes" => [
        [
          "name" => "file",
          "type" => "data-in",
          "portsIn" => [],
          "portsOut" => [ "in-data" => ['type' => 'file', 'value' => "join"] ]
        ]
      ]
    ]);

    $result = $this->helper->getVariablesForExercise(
      [
        $pipelineAid => $pipelineA,
        $pipelineBid => $pipelineB
      ],
      new VariablesTable());
    Assert::count(2, $result);
    Assert::true(array_key_exists($pipelineAid, $result));
    Assert::true(array_key_exists($pipelineBid, $result));

    Assert::count(2, $result[$pipelineAid]);
    Assert::count(0, $result[$pipelineBid]);

    Assert::equal("actualFile", $result[$pipelineAid][0]->getName());
    Assert::equal("expectedFile", $result[$pipelineAid][1]->getName());
    Assert::equal(VariableTypes::$FILE_TYPE, $result[$pipelineAid][0]->getType());
    Assert::equal(VariableTypes::$FILE_TYPE, $result[$pipelineAid][1]->getType());
  }

  public function testVariablesForExerciseNonEmptyJoin() {
    $pipelineAid = "pipelineA";
    $pipelineA = $this->loader->loadPipeline([
      "variables" => [],
      "boxes" => [
        [
          "name" => "input",
          "type" => "data-in",
          "portsIn" => [],
          "portsOut" => [ "in-data" => ['type' => 'file', 'value' => "input"] ]
        ],
        [
          "name" => "file",
          "type" => "data-out",
          "portsIn" => ["out-data" => ['type' => 'file', 'value' => "join"]],
          "portsOut" => []
        ]
      ]
    ]);
    $pipelineBid = "pipelineB";
    $pipelineB = $this->loader->loadPipeline([
      "variables" => [],
      "boxes" => [
        [
          "name" => "test",
          "type" => "data-in",
          "portsIn" => [],
          "portsOut" => [ "in-data" => ['type' => 'file', 'value' => "test"] ]
        ],
        [
          "name" => "file",
          "type" => "data-in",
          "portsIn" => [],
          "portsOut" => [ "in-data" => ['type' => 'file', 'value' => "join"] ]
        ]
      ]
    ]);

    $result = $this->helper->getVariablesForExercise(
      [
        $pipelineAid => $pipelineA,
        $pipelineBid => $pipelineB
      ],
      new VariablesTable());
    Assert::count(2, $result);
    Assert::true(array_key_exists($pipelineAid, $result));
    Assert::true(array_key_exists($pipelineBid, $result));

    Assert::count(1, $result[$pipelineAid]);
    Assert::count(1, $result[$pipelineBid]);

    Assert::equal("input", $result[$pipelineAid][0]->getName());
    Assert::equal("test", $result[$pipelineBid][0]->getName());

    Assert::equal(VariableTypes::$REMOTE_FILE_TYPE, $result[$pipelineAid][0]->getType());
    Assert::equal(VariableTypes::$REMOTE_FILE_TYPE, $result[$pipelineBid][0]->getType());
  }

  public function testVariablesForExerciseVariableFromVariablesTable() {
    $pipelineAid = "pipelineA";
    $pipelineA = $this->loader->loadPipeline([
      "variables" => [],
      "boxes" => [
        [
          "name" => "input",
          "type" => "data-in",
          "portsIn" => [],
          "portsOut" => [ "in-data" => ['type' => 'file', 'value' => "input"] ]
        ],
        [
          "name" => "file",
          "type" => "data-out",
          "portsIn" => ["out-data" => ['type' => 'file', 'value' => "join"]],
          "portsOut" => []
        ]
      ]
    ]);
    $pipelineBid = "pipelineB";
    $pipelineB = $this->loader->loadPipeline([
      "variables" => [],
      "boxes" => [
        [
          "name" => "test",
          "type" => "data-in",
          "portsIn" => [],
          "portsOut" => [ "in-data" => ['type' => 'file', 'value' => "test"] ]
        ],
        [
          "name" => "file",
          "type" => "data-in",
          "portsIn" => [],
          "portsOut" => [ "in-data" => ['type' => 'file', 'value' => "join"] ]
        ]
      ]
    ]);
    $variablesTable = $this->loader->loadVariablesTable([
      [ "name" => "test", "type" => "file", "value" => "test.in" ]
    ]);

    $result = $this->helper->getVariablesForExercise(
      [
        $pipelineAid => $pipelineA,
        $pipelineBid => $pipelineB
      ],
      $variablesTable);
    Assert::count(2, $result);
    Assert::true(array_key_exists($pipelineAid, $result));
    Assert::true(array_key_exists($pipelineBid, $result));

    Assert::count(1, $result[$pipelineAid]);
    Assert::count(0, $result[$pipelineBid]);

    Assert::equal("input", $result[$pipelineAid][0]->getName());
    Assert::equal(VariableTypes::$REMOTE_FILE_TYPE, $result[$pipelineAid][0]->getType());
  }

  public function testVariablesForExerciseComplexJoin() {
    $pipelineAid = "pipelineA";
    $pipelineA = $this->loader->loadPipeline([
      "variables" => [],
      "boxes" => [
        [
          "name" => "input",
          "type" => "data-in",
          "portsIn" => [],
          "portsOut" => [ "in-data" => ['type' => 'file', 'value' => "input"] ]
        ],
        [
          "name" => "file",
          "type" => "data-out",
          "portsIn" => ["out-data" => ['type' => 'file', 'value' => "join"]],
          "portsOut" => []
        ]
      ]
    ]);
    $pipelineBid = "pipelineB";
    $pipelineB = $this->loader->loadPipeline([
      "variables" => [],
      "boxes" => [
        [
          "name" => "test",
          "type" => "data-in",
          "portsIn" => [],
          "portsOut" => [ "in-data" => ['type' => 'file', 'value' => "test"] ]
        ],
        [
          "name" => "file",
          "type" => "data-in",
          "portsIn" => [],
          "portsOut" => [ "in-data" => ['type' => 'file', 'value' => "join"] ]
        ],
        [
          "name" => "output",
          "type" => "data-out",
          "portsIn" => ["out-data" => ['type' => 'file', 'value' => "join-second"]],
          "portsOut" => []
        ]
      ]
    ]);
    $pipelineCid = "pipelineC";
    $pipelineC = $this->loader->loadPipeline([
      "variables" => [],
      "boxes" => [
        [
          "name" => "environment",
          "type" => "data-in",
          "portsIn" => [],
          "portsOut" => [ "in-data" => ['type' => 'string', 'value' => "environment"] ]
        ],
        [
          "name" => "non-environment-a",
          "type" => "data-in",
          "portsIn" => [],
          "portsOut" => [ "in-data" => ['type' => 'string', 'value' => "non-environment-a"] ]
        ],
        [
          "name" => "non-environment-b",
          "type" => "data-in",
          "portsIn" => [],
          "portsOut" => [ "in-data" => ['type' => 'string', 'value' => "non-environment-b"] ]
        ],
        [
          "name" => "input",
          "type" => "data-in",
          "portsIn" => [],
          "portsOut" => [ "in-data" => ['type' => 'file', 'value' => "join-second"] ]
        ]
      ]
    ]);
    $variablesTable = $this->loader->loadVariablesTable([
      [ "name" => "test", "type" => "file", "value" => "test.in" ],
      [ "name" => "environment", "type" => "string", "value" => "environment" ]
    ]);

    $result = $this->helper->getVariablesForExercise(
      [
        $pipelineAid => $pipelineA,
        $pipelineBid => $pipelineB,
        $pipelineCid => $pipelineC
      ],
      $variablesTable);
    Assert::count(3, $result);
    Assert::true(array_key_exists($pipelineAid, $result));
    Assert::true(array_key_exists($pipelineBid, $result));
    Assert::true(array_key_exists($pipelineCid, $result));

    Assert::count(1, $result[$pipelineAid]);
    Assert::count(0, $result[$pipelineBid]);
    Assert::count(2, $result[$pipelineCid]);

    Assert::equal("input", $result[$pipelineAid][0]->getName());
    Assert::equal("non-environment-a", $result[$pipelineCid][0]->getName());
    Assert::equal("non-environment-b", $result[$pipelineCid][1]->getName());

    Assert::equal(VariableTypes::$REMOTE_FILE_TYPE, $result[$pipelineAid][0]->getType());
    Assert::equal(VariableTypes::$STRING_TYPE, $result[$pipelineCid][0]->getType());
    Assert::equal(VariableTypes::$STRING_TYPE, $result[$pipelineCid][1]->getType());
  }

}

# Testing methods run
$testCase = new TestExerciseConfigHelper;
$testCase->run();
