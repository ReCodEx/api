<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Exceptions\NotFoundException;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Compilation\PipelinesMerger;
use App\Helpers\ExerciseConfig\Compilation\Tree\MergeTree;
use App\Helpers\ExerciseConfig\Compilation\Tree\PortNode;
use App\Helpers\ExerciseConfig\Compilation\VariablesResolver;
use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Pipeline\Box\Box;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Helpers\ExerciseConfig\Pipeline\Box\DataInBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\FileInBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\FileOutBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\FilesInBox;
use App\Helpers\ExerciseConfig\VariablesTable;
use App\Model\Repository\Pipelines;
use Tester\Assert;


class TestPipelinesMerger extends Tester\TestCase
{
  /** @var BoxService */
  private $boxService;

  /** @var Loader */
  private $loader;

  /** @var Mockery\Mock | Pipelines */
  private $mockPipelines;

  /** @var Mockery\Mock | \App\Model\Entity\Pipeline */
  private $mockCompilationPipeline;
  /** @var Mockery\Mock | \App\Model\Entity\Pipeline */
  private $mockTestPipeline;

  /** @var Mockery\Mock | \App\Model\Entity\PipelineConfig */
  private $mockCompilationPipelineConfig;
  /** @var Mockery\Mock | \App\Model\Entity\PipelineConfig */
  private $mockTestPipelineConfig;

  /** @var PipelinesMerger */
  private $merger;


  private static $config;
  private static $envVariablesTable;
  private static $environment;
  private static $compilationPipeline;
  private static $testPipeline;


  private function setUpMocks() {
    // mock pipelines repository
    $this->mockPipelines = Mockery::mock(Pipelines::class);
    // construct all services
    $this->boxService = new BoxService();
    $this->loader = new Loader($this->boxService);
    $this->merger = new PipelinesMerger($this->mockPipelines, $this->loader, new VariablesResolver());

    // mock entities and stuff
    $this->mockCompilationPipelineConfig = Mockery::mock(\App\Model\Entity\PipelineConfig::class);
    $this->mockCompilationPipeline = Mockery::mock(\App\Model\Entity\Pipeline::class);
    $this->mockCompilationPipeline->shouldReceive("getPipelineConfig")->andReturn($this->mockCompilationPipelineConfig);
    $this->mockTestPipelineConfig = Mockery::mock(\App\Model\Entity\PipelineConfig::class);
    $this->mockTestPipeline = Mockery::mock(\App\Model\Entity\Pipeline::class);
    $this->mockTestPipeline->shouldReceive("getPipelineConfig")->andReturn($this->mockTestPipelineConfig);
  }

  protected function setUp() {
    $this->setUpMocks();

    self::$config = [
      "environments" => [ "envA", "envB" ],
      "tests" => [
        "testA" => [
          "environments" => [
            "envA" => [ "pipelines" => [
              [ "name" => "compilationPipeline", "variables" => [] ],
              [ "name" => "testPipeline", "variables" => [ [ "name" => "expected_output", "type" => "file", "value" => "expected.out" ] ] ]
            ] ],
            "envB" => [ "pipelines" => [
              [ "name" => "compilationPipeline", "variables" => [] ],
              [ "name" => "testPipeline", "variables" => [ [ "name" => "expected_output", "type" => "file", "value" => "expected.out" ] ] ]
            ] ]
          ]
        ],
        "testB" => [
          "environments" => [
            "envA" => [
              "pipelines" => [
                [ "name" => "compilationPipeline", "variables" => [] ],
              ]
            ],
            "envB" => [
              "pipelines" => [
                [ "name" => "compilationPipeline", "variables" => [] ],
                [ "name" => "testPipeline", "variables" => [ [ "name" => "expected_output", "type" => "file", "value" => "expected.out" ] ] ]
              ]
            ]
          ]
        ]
      ]
    ];
    self::$envVariablesTable = [
      [ "name" => "source_files", "type" => "file[]", "value" => ["source"] ]
    ];
    self::$environment = "envA";

    self::$compilationPipeline = [
      "variables" => [
        [ "name" => "source_files", "type" => "file", "value" => "source" ],
        [ "name" => "binary_file", "type" => "file", "value" => "a.out" ]
      ],
      "boxes" => [
        [
          "name" => "source",
          "type" => "files-in",
          "portsIn" => [],
          "portsOut" => [
            "input" => [ "type" => "file[]", "value" => "source_files" ]
          ]
        ],
        [
          "name" => "compilation",
          "type" => "gcc",
          "portsIn" => [
            "args" => [ "type" => "string[]", "value" => "" ],
            "source-files" => [ "type" => "file[]", "value" => "source_files" ]
          ],
          "portsOut" => [
            "binary-file" => [ "type" => "file", "value" => "binary_file" ]
          ]
        ],
        [
          "name" => "output",
          "type" => "file-out",
          "portsIn" => [
            "output" => [ "type" => "file", "value" => "binary_file" ]
          ],
          "portsOut" => []
        ]
      ]
    ];
    self::$testPipeline = [
      "variables" => [
        [ "name" => "binary_file", "type" => "file", "value" => "a.out" ],
        [ "name" => "expected_output", "type" => "file", "value" => "expected.out" ],
        [ "name" => "actual_output", "type" => "file", "value" => "actual.out" ]
      ],
      "boxes" => [
        [
          "name" => "binary",
          "type" => "file-in",
          "portsIn" => [],
          "portsOut" => [
            "input" => [ "type" => "file", "value" => "binary_file" ]
          ]
        ],
        [
          "name" => "test",
          "type" => "file-in",
          "portsIn" => [],
          "portsOut" => [
            "input" => [ "type" => "file", "value" => "expected_output" ]
          ]
        ],
        [
          "name" => "run",
          "type" => "elf-exec",
          "portsIn" => [
            "args" => [ "type" => "string[]", "value" => "" ],
            "stdin" => [ "type" => "file", "value" => "" ],
            "binary-file" => [ "type" => "file", "value" => "binary_file" ],
            "input-files" => [ "type" => "file[]", "value" => "" ]
          ],
          "portsOut" => [
            "stdout" => [ "type" => "file", "value" => "" ],
            "output-file" => [ "type" => "file", "value" => "actual_output" ]
          ]
        ],
        [
          "name" => "judge",
          "type" => "judge",
          "portsIn" => [
            "judge-type" => [ "type" => "string", "value" => "" ],
            "args" => ['type' => 'string[]', 'value' => ""],
            "custom-judge" => ['type' => 'file', 'value' => ""],
            "actual-output" => [ "type" => "file", "value" => "actual_output" ],
            "expected-output" => [ "type" => "file", "value" => "expected_output" ]
          ],
          "portsOut" => []
        ]
      ]
    ];
  }


  public function testEmptyTests() {
    $this->mockCompilationPipelineConfig->shouldReceive("getParsedPipeline")->andReturn(self::$compilationPipeline);
    $this->mockTestPipelineConfig->shouldReceive("getParsedPipeline")->andReturn(self::$testPipeline);
    $this->mockPipelines->shouldReceive("findOrThrow")->with("compilationPipeline")->andReturn($this->mockCompilationPipeline);
    $this->mockPipelines->shouldReceive("findOrThrow")->with("testPipeline")->andReturn($this->mockTestPipeline);

    Assert::exception(function () {
      $this->merger->merge(new ExerciseConfig(), new VariablesTable(),
        self::$environment, CompilationParams::create());
    }, ExerciseConfigException::class);
  }

  public function testEmptyPipelines() {

    // configure configuration
    self::$config["tests"]["testA"]["pipelines"] = [];
    self::$config["tests"]["testB"]["pipelines"] = [];
    self::$config["tests"]["testB"]["environments"]["envA"]["pipelines"] = [];

    // create all needed stuff
    $config = $this->loader->loadExerciseConfig(self::$config);
    $envVariablesTable = $this->loader->loadVariablesTable(self::$envVariablesTable);
    $this->mockCompilationPipelineConfig->shouldReceive("getParsedPipeline")->andReturn(self::$compilationPipeline);
    $this->mockTestPipelineConfig->shouldReceive("getParsedPipeline")->andReturn(self::$testPipeline);
    $this->mockPipelines->shouldReceive("findOrThrow")->with("compilationPipeline")->andReturn($this->mockCompilationPipeline);
    $this->mockPipelines->shouldReceive("findOrThrow")->with("testPipeline")->andReturn($this->mockTestPipeline);

    Assert::exception(function () use ($config, $envVariablesTable) {
      $this->merger->merge($config, $envVariablesTable,
        self::$environment, CompilationParams::create());
    }, ExerciseConfigException::class);
  }

  public function testNonExistingPipeline() {
    $config = $this->loader->loadExerciseConfig(self::$config);
    $envVariablesTable = $this->loader->loadVariablesTable(self::$envVariablesTable);
    $this->mockPipelines->shouldReceive("findOrThrow")->withAnyArgs()->andThrow(NotFoundException::class);

    Assert::exception(function () use ($config, $envVariablesTable) {
      $this->merger->merge($config, $envVariablesTable, self::$environment,
        CompilationParams::create());
    }, ExerciseConfigException::class);
  }


  /**
   * Internal checking function for testA tree.
   * @param MergeTree $testA
   */
  private function checkTreeA(MergeTree $testA) {
    Assert::count(6, $testA->getAllNodes());
    Assert::count(2, $testA->getInputNodes());
    Assert::count(0, $testA->getOutputNodes());
    Assert::count(4, $testA->getOtherNodes());

    // prepare nodes

    $inputNodes = array(); /** @var PortNode[] $inputNodes */
    $outputNodes = array(); /** @var PortNode[] $outputNodes */
    $otherNodes = array(); /** @var PortNode[] $otherNodes */
    foreach ($testA->getInputNodes() as $inputNode) {
      Assert::type(DataInBox::class, $inputNode->getBox());
      $inputNodes[$inputNode->getBox()->getName()] = $inputNode;
    }
    foreach ($testA->getOutputNodes() as $outputNode) {
      Assert::type(FileOutBox::class, $outputNode->getBox());
      $outputNodes[$outputNode->getBox()->getName()] = $outputNode;
    }
    foreach ($testA->getOtherNodes() as $otherNode) {
      Assert::type(Box::class, $otherNode->getBox());
      $otherNodes[$otherNode->getBox()->getName()] = $otherNode;
    }

    // check boxes and its existence

    Assert::true(array_key_exists("source", $inputNodes));
    Assert::true(array_key_exists("test", $inputNodes));
    Assert::true(array_key_exists("compilation", $otherNodes));
    Assert::true(array_key_exists("output__binary__join-box", $otherNodes));
    Assert::true(array_key_exists("run", $otherNodes));
    Assert::true(array_key_exists("judge", $otherNodes));
    $sourceNode = $inputNodes["source"];
    $testNode = $inputNodes["test"];
    $compilationNode = $otherNodes["compilation"];
    $joinNode = $otherNodes["output__binary__join-box"];
    $runNode = $otherNodes["run"];
    $judgeNode = $otherNodes["judge"];

    Assert::equal("testA", $sourceNode->getTestId());
    Assert::equal("testA", $testNode->getTestId());
    Assert::equal("testA", $compilationNode->getTestId());
    Assert::equal("testA", $joinNode->getTestId());
    Assert::equal("testA", $runNode->getTestId());
    Assert::equal("testA", $judgeNode->getTestId());

    Assert::equal("compilationPipeline", $sourceNode->getPipelineId());
    Assert::equal("testPipeline", $testNode->getPipelineId());
    Assert::equal("compilationPipeline", $compilationNode->getPipelineId());
    Assert::equal("testPipeline", $joinNode->getPipelineId());
    Assert::equal("testPipeline", $runNode->getPipelineId());
    Assert::equal("testPipeline", $judgeNode->getPipelineId());

    // check connections between nodes

    Assert::count(1, $sourceNode->getChildren());
    Assert::same($compilationNode, current($sourceNode->getChildren()));

    Assert::count(1, $testNode->getChildren());
    Assert::same($judgeNode, current($testNode->getChildren()));

    Assert::count(1, $compilationNode->getParents());
    Assert::same($sourceNode, current($compilationNode->getParents()));
    Assert::count(1, $compilationNode->getChildren());
    Assert::same($joinNode, current($compilationNode->getChildren()));

    Assert::count(1, $joinNode->getParents());
    Assert::same($compilationNode, current($joinNode->getParents()));
    Assert::count(1, $joinNode->getChildren());
    Assert::same($runNode, current($joinNode->getChildren()));

    Assert::count(1, $runNode->getParents());
    Assert::same($joinNode, current($runNode->getParents()));
    Assert::count(1, $runNode->getChildren());
    Assert::same($judgeNode, current($runNode->getChildren()));

    Assert::count(2, $judgeNode->getParents());
    Assert::same($runNode, $judgeNode->getParents()["actual-output"]);
    Assert::same($testNode, $judgeNode->getParents()["expected-output"]);
    Assert::count(0, $judgeNode->getChildren());
  }

  /**
   * Internal checking function for testA tree.
   * @param MergeTree $testB
   */
  private function checkTreeB(MergeTree $testB) {
    Assert::count(3, $testB->getAllNodes());
    Assert::count(1, $testB->getInputNodes());
    Assert::count(1, $testB->getOutputNodes());
    Assert::count(1, $testB->getOtherNodes());

    // prepare nodes

    $inputNodes = array(); /** @var PortNode[] $inputNodes */
    $outputNodes = array(); /** @var PortNode[] $outputNodes */
    $otherNodes = array(); /** @var PortNode[] $otherNodes */
    foreach ($testB->getInputNodes() as $inputNode) {
      Assert::type(DataInBox::class, $inputNode->getBox());
      $inputNodes[$inputNode->getBox()->getName()] = $inputNode;
    }
    foreach ($testB->getOutputNodes() as $outputNode) {
      Assert::type(FileOutBox::class, $outputNode->getBox());
      $outputNodes[$outputNode->getBox()->getName()] = $outputNode;
    }
    foreach ($testB->getOtherNodes() as $otherNode) {
      Assert::type(Box::class, $otherNode->getBox());
      $otherNodes[$otherNode->getBox()->getName()] = $otherNode;
    }

    // check boxes and its existence

    Assert::true(array_key_exists("source", $inputNodes));
    Assert::true(array_key_exists("compilation", $otherNodes));
    Assert::true(array_key_exists("output", $outputNodes));
    $sourceNode = $inputNodes["source"];
    $compilationNode = $otherNodes["compilation"];
    $outputNode = $outputNodes["output"];

    Assert::equal("testB", $sourceNode->getTestId());
    Assert::equal("testB", $compilationNode->getTestId());
    Assert::equal("testB", $outputNode->getTestId());

    Assert::equal("compilationPipeline", $sourceNode->getPipelineId());
    Assert::equal("compilationPipeline", $compilationNode->getPipelineId());
    Assert::equal("compilationPipeline", $outputNode->getPipelineId());

    // check connections between nodes

    Assert::count(1, $sourceNode->getChildren());
    Assert::same($compilationNode, current($sourceNode->getChildren()));

    Assert::count(1, $compilationNode->getParents());
    Assert::same($sourceNode, current($compilationNode->getParents()));
    Assert::count(1, $compilationNode->getChildren());
    Assert::same($outputNode, current($compilationNode->getChildren()));

    Assert::count(1, $outputNode->getParents());
    Assert::same($compilationNode, current($outputNode->getParents()));
    Assert::count(0, $outputNode->getChildren());
  }

  public function testCorrect() {
    $config = $this->loader->loadExerciseConfig(self::$config);
    $envVariablesTable = $this->loader->loadVariablesTable(self::$envVariablesTable);
    $this->mockCompilationPipelineConfig->shouldReceive("getParsedPipeline")->andReturn(self::$compilationPipeline);
    $this->mockTestPipelineConfig->shouldReceive("getParsedPipeline")->andReturn(self::$testPipeline);
    $this->mockPipelines->shouldReceive("findOrThrow")->with("compilationPipeline")->andReturn($this->mockCompilationPipeline);
    $this->mockPipelines->shouldReceive("findOrThrow")->with("testPipeline")->andReturn($this->mockTestPipeline);

    $tests = $this->merger->merge($config, $envVariablesTable,
      self::$environment, CompilationParams::create());
    Assert::count(2, $tests);

    $testA = $tests["testA"];
    $testB = $tests["testB"];
    Assert::type(MergeTree::class, $testA);
    Assert::type(MergeTree::class, $testB);

    // check test A
    $this->checkTreeA($testA);

    // check test B
    $this->checkTreeB($testB);
  }

}

# Testing methods run
$testCase = new TestPipelinesMerger();
$testCase->run();
