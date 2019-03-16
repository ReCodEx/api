<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseCompilationException;
use App\Exceptions\NotFoundException;
use App\Helpers\ExerciseConfig\Compilation\CompilationContext;
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
use App\Helpers\ExerciseConfig\Pipeline\Box\FileOutBox;
use App\Helpers\ExerciseConfig\PipelinesCache;
use App\Helpers\ExerciseConfig\VariablesTable;
use Tester\Assert;


/**
 * @testCase
 */
class TestPipelinesMerger extends Tester\TestCase
{
  /** @var BoxService */
  private $boxService;

  /** @var Loader */
  private $loader;

  /** @var Mockery\Mock | PipelinesCache */
  private $mockPipelinesCache;

  /** @var Mockery\Mock | \App\Model\Entity\Pipeline */
  private $mockCompilationPipeline;
  /** @var Mockery\Mock | \App\Model\Entity\Pipeline */
  private $mockTestPipeline;

  /** @var PipelinesMerger */
  private $merger;


  private static $config;
  private static $envVariablesTable;
  private static $environment;
  private static $compilationPipeline;
  private static $testPipeline;
  private static $testsNames;
  private static $exerciseFiles = [];
  private static $pipelineFiles = [];


  private function setUpMocks() {
    // mock pipelines repository
    $this->mockPipelinesCache = Mockery::mock(PipelinesCache::class);
    // construct all services
    $this->boxService = new BoxService();
    $this->loader = new Loader($this->boxService);
    $this->merger = new PipelinesMerger($this->mockPipelinesCache, new VariablesResolver());

    // mock entities and stuff
    $this->mockCompilationPipeline = Mockery::mock(\App\Model\Entity\Pipeline::class);
    $this->mockCompilationPipeline->shouldReceive("getHashedSupplementaryFiles")->andReturn(self::$pipelineFiles);
    $this->mockTestPipeline = Mockery::mock(\App\Model\Entity\Pipeline::class);
    $this->mockTestPipeline->shouldReceive("getHashedSupplementaryFiles")->andReturn(self::$pipelineFiles);
  }

  protected function setUp() {
    $this->setUpMocks();

    self::$testsNames = [
      "1" => "testA",
      "2" => "testB"
    ];
    self::$config = [
      "environments" => [ "envA", "envB" ],
      "tests" => [
        "1" => [
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
        "2" => [
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
            "source-files" => [ "type" => "file[]", "value" => "source_files" ],
            "extra-files" => [ "type" => "file[]", "value" => "" ]
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
    $this->mockPipelinesCache->shouldReceive("getPipeline")->with("compilationPipeline")->andReturn($this->mockCompilationPipeline);
    $this->mockPipelinesCache->shouldReceive("getPipeline")->with("testPipeline")->andReturn($this->mockTestPipeline);
    $this->mockPipelinesCache->shouldReceive("getNewPipelineConfig")->with("compilationPipeline")
      ->andReturn($this->loader->loadPipeline(self::$compilationPipeline));
    $this->mockPipelinesCache->shouldReceive("getNewPipelineConfig")->with("testPipeline")
      ->andReturn($this->loader->loadPipeline(self::$testPipeline));

    Assert::exception(function () {
      $context = CompilationContext::create(new ExerciseConfig(), new VariablesTable(), [], self::$exerciseFiles, self::$testsNames, self::$environment);
      $this->merger->merge($context, CompilationParams::create());
    }, ExerciseCompilationException::class);
  }

  public function testEmptyPipelines() {

    // configure configuration
    self::$config["tests"]["1"]["pipelines"] = [];
    self::$config["tests"]["2"]["pipelines"] = [];
    self::$config["tests"]["2"]["environments"]["envA"]["pipelines"] = [];

    // create all needed stuff
    $config = $this->loader->loadExerciseConfig(self::$config);
    $envVariablesTable = $this->loader->loadVariablesTable(self::$envVariablesTable);
    $this->mockPipelinesCache->shouldReceive("getPipeline")->with("compilationPipeline")->andReturn($this->mockCompilationPipeline);
    $this->mockPipelinesCache->shouldReceive("getPipeline")->with("testPipeline")->andReturn($this->mockTestPipeline);
    $this->mockPipelinesCache->shouldReceive("getNewPipelineConfig")->with("compilationPipeline")
      ->andReturn($this->loader->loadPipeline(self::$compilationPipeline));
    $this->mockPipelinesCache->shouldReceive("getNewPipelineConfig")->with("testPipeline")
      ->andReturn($this->loader->loadPipeline(self::$testPipeline));

    Assert::exception(function () use ($config, $envVariablesTable) {
      $context = CompilationContext::create($config, $envVariablesTable, [], self::$exerciseFiles, self::$testsNames, self::$environment);
      $this->merger->merge($context, CompilationParams::create());
    }, ExerciseCompilationException::class);
  }

  public function testNonExistingPipeline() {
    $config = $this->loader->loadExerciseConfig(self::$config);
    $envVariablesTable = $this->loader->loadVariablesTable(self::$envVariablesTable);
    $this->mockPipelinesCache->shouldReceive("getPipeline")->withAnyArgs()->andThrow(NotFoundException::class);

    Assert::exception(function () use ($config, $envVariablesTable) {
      $context = CompilationContext::create($config, $envVariablesTable, [], self::$exerciseFiles, self::$testsNames, self::$environment);
      $this->merger->merge($context, CompilationParams::create());
    }, ExerciseCompilationException::class);
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

    Assert::equal("1", $sourceNode->getTestId());
    Assert::equal("1", $testNode->getTestId());
    Assert::equal("1", $compilationNode->getTestId());
    Assert::equal("1", $joinNode->getTestId());
    Assert::equal("1", $runNode->getTestId());
    Assert::equal("1", $judgeNode->getTestId());

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

    Assert::equal("2", $sourceNode->getTestId());
    Assert::equal("2", $compilationNode->getTestId());
    Assert::equal("2", $outputNode->getTestId());

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
    $this->mockPipelinesCache->shouldReceive("getPipeline")->with("compilationPipeline")->andReturn($this->mockCompilationPipeline);
    $this->mockPipelinesCache->shouldReceive("getPipeline")->with("testPipeline")->andReturn($this->mockTestPipeline);
    $this->mockPipelinesCache->shouldReceive("getNewPipelineConfig")->with("compilationPipeline")
      ->andReturn($this->loader->loadPipeline(self::$compilationPipeline));
    $this->mockPipelinesCache->shouldReceive("getNewPipelineConfig")->with("testPipeline")
      ->andReturn($this->loader->loadPipeline(self::$testPipeline));

    $context = CompilationContext::create($config, $envVariablesTable, [], self::$exerciseFiles, self::$testsNames, self::$environment);
    $tests = $this->merger->merge($context, CompilationParams::create());
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
