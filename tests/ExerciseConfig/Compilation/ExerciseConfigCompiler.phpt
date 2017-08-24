<?php

include '../../bootstrap.php';

use App\Helpers\ExerciseConfig\Compilation\BoxesCompiler;
use App\Helpers\ExerciseConfig\Compilation\BoxesOptimizer;
use App\Helpers\ExerciseConfig\Compilation\BoxesSorter;
use App\Helpers\ExerciseConfig\Compilation\ExerciseConfigCompiler;
use App\Helpers\ExerciseConfig\Compilation\PipelinesMerger;
use App\Helpers\ExerciseConfig\Compilation\TestDirectoriesResolver;
use App\Helpers\ExerciseConfig\Compilation\VariablesResolver;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Model\Repository\Pipelines;
use Tester\Assert;


/**
 * All special cases should be resolved in appropriate tests. This test is only
 * integration test of all compiler components and if it is working as expected.
 */
class TestExerciseConfigCompiler extends Tester\TestCase
{
  /** @var ExerciseConfigCompiler */
  private $compiler;
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


  private static $exerciseConfig = [
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
            ]
          ],
          "envB" => [
            "pipelines" => []
          ]
        ]
      ]
    ]
  ];
  private static $envVariablesTable = [
    [ "name" => "source_files", "type" => "file[]", "value" => ["source"] ]
  ];
  private static $environment = "envA";
  private static $compilationPipeline = [
    "variables" => [
      ["name" => "source_files", "type" => "file[]", "value" => ["source"]],
      ["name" => "binary_file", "type" => "file", "value" => "a.out"]
    ],
    "boxes" => [
      [
        "name" => "source",
        "type" => "data-in",
        "portsIn" => [],
        "portsOut" => [
          "in-data" => ["type" => "file[]", "value" => "source_files"]
        ]
      ],
      [
        "name" => "compilation",
        "type" => "gcc",
        "portsIn" => [
          "source-files" => ["type" => "file[]", "value" => "source_files"]
        ],
        "portsOut" => [
          "binary-file" => ["type" => "file", "value" => "binary_file"]
        ]
      ],
      [
        "name" => "output",
        "type" => "data-out",
        "portsIn" => [
          "out-data" => ["type" => "file", "value" => "binary_file"]
        ],
        "portsOut" => []
      ]
    ]
  ];
  private static $testPipeline = [
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
        "portsOut" => []
      ]
    ]
  ];


  /**
   * TestExerciseConfigCompiler constructor.
   */
  public function __construct() {

    // mock entities and stuff
    $this->mockCompilationPipelineConfig = Mockery::mock(\App\Model\Entity\PipelineConfig::class);
    $this->mockCompilationPipeline = Mockery::mock(\App\Model\Entity\Pipeline::class);
    $this->mockCompilationPipeline->shouldReceive("getPipelineConfig")->andReturn($this->mockCompilationPipelineConfig);
    $this->mockTestPipelineConfig = Mockery::mock(\App\Model\Entity\PipelineConfig::class);
    $this->mockTestPipeline = Mockery::mock(\App\Model\Entity\Pipeline::class);
    $this->mockTestPipeline->shouldReceive("getPipelineConfig")->andReturn($this->mockTestPipelineConfig);

    $this->mockCompilationPipelineConfig->shouldReceive("getParsedPipeline")->andReturn(self::$compilationPipeline);
    $this->mockTestPipelineConfig->shouldReceive("getParsedPipeline")->andReturn(self::$testPipeline);

    $this->mockPipelines = Mockery::mock(Pipelines::class);
    $this->mockPipelines->shouldReceive("findOrThrow")->with("compilationPipeline")->andReturn($this->mockCompilationPipeline);
    $this->mockPipelines->shouldReceive("findOrThrow")->with("testPipeline")->andReturn($this->mockTestPipeline);

    // constructions of compiler components
    $this->loader = new Loader(new BoxService());
    $variablesResolver = new VariablesResolver();
    $pipelinesMerger = new PipelinesMerger($this->mockPipelines, $this->loader, $variablesResolver);
    $boxesSorter = new BoxesSorter();
    $boxesOptimizer = new BoxesOptimizer();
    $boxesCompiler = new BoxesCompiler();
    $testDirectoriesResolver = new TestDirectoriesResolver();
    $this->compiler = new ExerciseConfigCompiler($pipelinesMerger, $boxesSorter,
      $boxesOptimizer, $boxesCompiler, $testDirectoriesResolver);
  }

  public function testCorrect() {
    $exerciseConfig = $this->loader->loadExerciseConfig(self::$exerciseConfig);
    $environmentConfigVariables = $this->loader->loadVariablesTable(self::$envVariablesTable);
    $limits = array(); // @todo

    $jobConfig = $this->compiler->compile($exerciseConfig, $environmentConfigVariables, $limits, self::$environment);

    // @todo

    Assert::true(false);
  }

}

# Testing methods run
$testCase = new TestExerciseConfigCompiler();
$testCase->run();
