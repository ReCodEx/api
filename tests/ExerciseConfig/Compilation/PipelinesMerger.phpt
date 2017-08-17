<?php

include '../../bootstrap.php';

use App\Helpers\ExerciseConfig\Compilation\PipelinesMerger;
use App\Helpers\ExerciseConfig\Compilation\Tree\MergeTree;
use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Helpers\ExerciseConfig\VariablesTable;
use App\Model\Repository\Pipelines;
use Tester\Assert;


class TestPipelinesMerger extends Tester\TestCase
{
  /** @var BoxService */
  private $boxService;

  /** @var Loader */
  private $loader;

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


  public function __construct() {

    // mock pipelines repository
    $mockPipelines = Mockery::mock(Pipelines::class);
    // construct all services
    $this->boxService = new BoxService();
    $this->loader = new Loader($this->boxService);
    $this->merger = new PipelinesMerger($mockPipelines, $this->loader);

    // mock entities and stuff
    $this->mockCompilationPipelineConfig = Mockery::mock(\App\Model\Entity\PipelineConfig::class);
    $mockCompilationPipeline = Mockery::mock(\App\Model\Entity\Pipeline::class);
    $mockCompilationPipeline->shouldReceive("getPipelineConfig")->andReturn($this->mockCompilationPipelineConfig);
    $this->mockTestPipelineConfig = Mockery::mock(\App\Model\Entity\PipelineConfig::class);
    $mockTestPipeline = Mockery::mock(\App\Model\Entity\Pipeline::class);
    $mockTestPipeline->shouldReceive("getPipelineConfig")->andReturn($this->mockTestPipelineConfig);

    // set up mocked pipelines repository
    $mockPipelines->shouldReceive("findOrThrow")->with("hello")->andReturn($mockCompilationPipeline);
    $mockPipelines->shouldReceive("findOrThrow")->with("world")->andReturn($mockTestPipeline);
  }


  protected function setUp() {
    self::$config = [
      "environments" => [ "envA", "envB" ],
      "tests" => [
        "testA" => [
          "pipelines" => [
            [ "name" => "compilationPipeline", "variables" => [] ],
            [ "name" => "testPipeline", "variables" => [ [ "name" => "expected_output", "type" => "file", "value" => "expected.out" ] ] ]
          ],
          "environments" => [
            "envA" => [ "pipelines" => [] ],
            "envB" => [ "pipelines" => [] ]
          ]
        ],
        "testB" => [
          "pipelines" => [
            [ "name" => "compilationPipeline", "variables" => [] ],
            [ "name" => "testPipeline", "variables" => [ [ "name" => "expected_output", "type" => "file", "value" => "expected.out" ] ] ]
          ],
          "environments" => [
            "envA" => [
              "pipelines" => [
                [ "name" => "compilationPipeline", "variables" => [] ],
                [ "name" => "testPipeline", "variables" => [ [ "name" => "expected_output", "type" => "file", "value" => "expected.envA.out" ] ] ]
              ]
            ],
            "envB" => [
              "pipelines" => []
            ]
          ]
        ]
      ]
    ];
    self::$envVariablesTable = [
      [ "name" => "source_file", "type" => "file", "value" => "source" ]
    ];
    self::$environment = "envA";

    self::$compilationPipeline = [
      "variables" => [
        [ "name" => "source_file", "type" => "file", "value" => "source" ],
        [ "name" => "binary_file", "type" => "file", "value" => "a.out" ]
      ],
      "boxes" => [
        [
          "name" => "source",
          "type" => "data-in",
          "portsIn" => [],
          "portsOut" => [
            "in-data" => [ "type" => "file", "value" => "source_file" ]
          ]
        ],
        [
          "name" => "compilation",
          "type" => "gcc",
          "portsIn" => [
            "source-file" => [ "type" => "file", "value" => "source_file" ]
          ],
          "portsOut" => [
            "binary-file" => [ "type" => "file", "value" => "binary_file" ]
          ]
        ],
        [
          "name" => "output",
          "type" => "data-out",
          "portsIn" => [
            "out-data" => [ "type" => "file", "value" => "binary_file" ]
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
          "type" => "data-in",
          "portsIn" => [],
          "portsOut" => [
            "in-data" => [ "type" => "file", "value" => "binary_file" ]
          ]
        ],
        [
          "name" => "test",
          "type" => "data-in",
          "portsIn" => [],
          "portsOut" => [
            "in-data" => [ "type" => "file", "value" => "expected_output" ]
          ]
        ],
        [
          "name" => "run",
          "type" => "elf-exec",
          "portsIn" => [
            "binary-file" => [ "type" => "file", "value" => "binary_file" ]
          ],
          "portsOut" => [
            "output-file" => [ "type" => "file", "value" => "actual_output" ]
          ]
        ],
        [
          "name" => "judge",
          "type" => "judge-normal",
          "portsIn" => [
            "actual-output" => [ "type" => "file", "value" => "actual_output" ],
            "expected-output" => [ "type" => "file", "value" => "expected_output" ]
          ],
          "portsOut" => [
            "score" => [ "type" => "string", "value" => "" ]
          ]
        ]
      ]
    ];
  }


  public function testEmptyTests() {
    $this->mockCompilationPipelineConfig->shouldReceive("getParsedPipeline")->andReturn(self::$compilationPipeline);
    $this->mockTestPipelineConfig->shouldReceive("getParsedPipeline")->andReturn(self::$testPipeline);

    $tests = $this->merger->merge(new ExerciseConfig(), new VariablesTable(), self::$environment);
    Assert::true(is_array($tests));
    Assert::count(0, $tests);
  }

  public function testEmptyPipelines() {
    Assert::true(false);
  }

  public function testNonExistingPipeline() {
    Assert::true(false);
  }

  public function testCorrect() {
    $config = $this->loader->loadExerciseConfig(self::$config);
    $envVariablesTable = $this->loader->loadVariablesTable(self::$envVariablesTable);
    $this->mockCompilationPipelineConfig->shouldReceive("getParsedPipeline")->andReturn(self::$compilationPipeline);
    $this->mockTestPipelineConfig->shouldReceive("getParsedPipeline")->andReturn(self::$testPipeline);
    $tests = $this->merger->merge($config, $envVariablesTable, self::$environment);
    Assert::count(2, $tests);

    $testA = $tests["testA"];
    $testB = $tests["testB"];
    Assert::type(MergeTree::class, $testA);
    Assert::type(MergeTree::class, $testB);

    // check test A

    // @todo: check proper merge
    // @todo: check variables setter
    // @todo: check input, output and other nodes in tree

    // check test B
    Assert::true(false);
  }

}

# Testing methods run
$testCase = new TestPipelinesMerger();
$testCase->run();
