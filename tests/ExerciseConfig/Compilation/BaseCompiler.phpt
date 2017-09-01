<?php

include '../../bootstrap.php';

use App\Helpers\ExerciseConfig\Compilation\BoxesCompiler;
use App\Helpers\ExerciseConfig\Compilation\BoxesOptimizer;
use App\Helpers\ExerciseConfig\Compilation\BoxesSorter;
use App\Helpers\ExerciseConfig\Compilation\BaseCompiler;
use App\Helpers\ExerciseConfig\Compilation\PipelinesMerger;
use App\Helpers\ExerciseConfig\Compilation\TestDirectoriesResolver;
use App\Helpers\ExerciseConfig\Compilation\VariablesResolver;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Helpers\ExerciseConfig\Pipeline\Box\GccCompilationBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\JudgeNormalBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\LinuxSandbox;
use App\Helpers\ExerciseConfig\Pipeline\Box\TaskType;
use App\Model\Repository\Pipelines;
use Tester\Assert;


/**
 * All special cases should be resolved in appropriate tests. This test is only
 * integration test of all compiler components and if it is working as expected.
 */
class TestBaseCompiler extends Tester\TestCase
{
  /** @var BaseCompiler */
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
          [ "name" => "testPipeline", "variables" => [ [ "name" => "expected_output", "type" => "remote-file", "value" => "expected.A.out" ] ] ]
        ],
        "environments" => [
          "envA" => [ "pipelines" => [] ],
          "envB" => [ "pipelines" => [] ]
        ]
      ],
      "testB" => [
        "pipelines" => [
          [ "name" => "compilationPipeline", "variables" => [] ],
          [ "name" => "testPipeline", "variables" => [ [ "name" => "expected_output", "type" => "remote-file", "value" => "expected.B.out" ] ] ]
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
  private static $limits = [
    [ // groupA
      "testA" => [
        "compilationPipeline" => [
          "compilation" => [
            "memory" => 123,
            "wall-time" => 456.0
          ]
        ],
        "testPipeline" => [
          "run" => [
            "memory" => 654,
            "wall-time" => 321.0
          ]
        ]
      ],
      "testB" => [
        "compilationPipeline" => [
          "compilation" => [
            "memory" => 789,
            "wall-time" => 987.0
          ]
        ]
      ]
    ],
    [ // groupB
      "testA" => [
        "compilationPipeline" => [
          "compilation" => [
            "memory" => 123,
            "wall-time" => 456.0
          ]
        ],
        "testPipeline" => [
          "run" => [
            "memory" => 654,
            "wall-time" => 321.0
          ]
        ]
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
    $this->compiler = new BaseCompiler($pipelinesMerger, $boxesSorter,
      $boxesOptimizer, $boxesCompiler, $testDirectoriesResolver);
  }

  public function testCorrect() {
    $exerciseConfig = $this->loader->loadExerciseConfig(self::$exerciseConfig);
    $environmentConfigVariables = $this->loader->loadVariablesTable(self::$envVariablesTable);
    $limits = [
      "groupA" => $this->loader->loadExerciseLimits(self::$limits[0]),
      "groupB" => $this->loader->loadExerciseLimits(self::$limits[1])
    ];
    $jobConfig = $this->compiler->compile($exerciseConfig, $environmentConfigVariables, $limits, self::$environment, []);

    // check general properties
    Assert::equal(["groupA", "groupB"], $jobConfig->getSubmissionHeader()->getHardwareGroups());
    Assert::equal(5, $jobConfig->getTasksCount());

    ////////////////////////////////////////////////////////////////////////////
    // check order of all tasks and right attributes
    //

    $testATestTask = $jobConfig->getTasks()[0];
    Assert::equal("testA.testPipeline.test.1", $testATestTask->getId());
    Assert::equal(1, $testATestTask->getPriority());
    Assert::count(0, $testATestTask->getDependencies());
    Assert::equal("fetch", $testATestTask->getCommandBinary());
    Assert::equal(["expected.A.out", "expected.out"], $testATestTask->getCommandArguments());
    Assert::null($testATestTask->getType());
    Assert::equal("testA", $testATestTask->getTestId());
    Assert::null($testATestTask->getSandboxConfig());

    $testACompilationTask = $jobConfig->getTasks()[1];
    Assert::equal("testA.compilationPipeline.compilation.2", $testACompilationTask->getId());
    Assert::equal(2, $testACompilationTask->getPriority());
    Assert::count(1, $testACompilationTask->getDependencies());
    Assert::equal([$testATestTask->getId()], $testACompilationTask->getDependencies());
    Assert::equal(GccCompilationBox::$GCC_BINARY, $testACompilationTask->getCommandBinary());
    Assert::equal(["source", "-o", "a.out"], $testACompilationTask->getCommandArguments());
    Assert::equal(TaskType::$INITIATION, $testACompilationTask->getType());
    Assert::equal("testA", $testACompilationTask->getTestId());
    Assert::notEqual(null, $testACompilationTask->getSandboxConfig());
    Assert::equal(LinuxSandbox::$ISOLATE, $testACompilationTask->getSandboxConfig()->getName());
    Assert::count(2, $testACompilationTask->getSandboxConfig()->getLimitsArray());
    Assert::equal(123, $testACompilationTask->getSandboxConfig()->getLimits("groupA")->getMemoryLimit());
    Assert::equal(456.0, $testACompilationTask->getSandboxConfig()->getLimits("groupA")->getWallTime());
    Assert::equal(123, $testACompilationTask->getSandboxConfig()->getLimits("groupB")->getMemoryLimit());
    Assert::equal(456.0, $testACompilationTask->getSandboxConfig()->getLimits("groupB")->getWallTime());

    $testARunTask = $jobConfig->getTasks()[2];
    Assert::equal("testA.testPipeline.run.3", $testARunTask->getId());
    Assert::equal(3, $testARunTask->getPriority());
    Assert::count(1, $testARunTask->getDependencies());
    Assert::equal([$testACompilationTask->getId()], $testARunTask->getDependencies());
    Assert::equal("a.out", $testARunTask->getCommandBinary());
    Assert::equal([], $testARunTask->getCommandArguments());
    Assert::equal(TaskType::$EXECUTION, $testARunTask->getType());
    Assert::equal("testA", $testARunTask->getTestId());
    Assert::notEqual(null, $testARunTask->getSandboxConfig());
    Assert::equal(LinuxSandbox::$ISOLATE, $testARunTask->getSandboxConfig()->getName());
    Assert::count(2, $testARunTask->getSandboxConfig()->getLimitsArray());
    Assert::equal(654, $testARunTask->getSandboxConfig()->getLimits("groupA")->getMemoryLimit());
    Assert::equal(321.0, $testARunTask->getSandboxConfig()->getLimits("groupA")->getWallTime());
    Assert::equal(654, $testARunTask->getSandboxConfig()->getLimits("groupB")->getMemoryLimit());
    Assert::equal(321.0, $testARunTask->getSandboxConfig()->getLimits("groupB")->getWallTime());

    $testAJudgeTask = $jobConfig->getTasks()[3];
    Assert::equal("testA.testPipeline.judge.4", $testAJudgeTask->getId());
    Assert::equal(4, $testAJudgeTask->getPriority());
    Assert::count(1, $testAJudgeTask->getDependencies());
    Assert::equal([$testARunTask->getId()], $testAJudgeTask->getDependencies());
    Assert::equal(JudgeNormalBox::$JUDGE_NORMAL_BINARY, $testAJudgeTask->getCommandBinary());
    Assert::equal(["expected.out", "actual.out"], $testAJudgeTask->getCommandArguments());
    Assert::equal(TaskType::$EVALUATION, $testAJudgeTask->getType());
    Assert::equal("testA", $testAJudgeTask->getTestId());
    Assert::notEqual(null, $testAJudgeTask->getSandboxConfig());
    Assert::equal(LinuxSandbox::$ISOLATE, $testAJudgeTask->getSandboxConfig()->getName());
    Assert::count(0, $testAJudgeTask->getSandboxConfig()->getLimitsArray());

    $testBCompilationTask = $jobConfig->getTasks()[4];
    Assert::equal("testB.compilationPipeline.compilation.5", $testBCompilationTask->getId());
    Assert::equal(5, $testBCompilationTask->getPriority());
    Assert::count(0, $testBCompilationTask->getDependencies());
    Assert::equal(GccCompilationBox::$GCC_BINARY, $testBCompilationTask->getCommandBinary());
    Assert::equal(["source", "-o", "a.out"], $testBCompilationTask->getCommandArguments());
    Assert::equal(TaskType::$INITIATION, $testBCompilationTask->getType());
    Assert::equal("testB", $testBCompilationTask->getTestId());
    Assert::notEqual(null, $testBCompilationTask->getSandboxConfig());
    Assert::equal(LinuxSandbox::$ISOLATE, $testBCompilationTask->getSandboxConfig()->getName());
    Assert::count(1, $testBCompilationTask->getSandboxConfig()->getLimitsArray());
    Assert::equal(789, $testBCompilationTask->getSandboxConfig()->getLimits("groupA")->getMemoryLimit());
    Assert::equal(987.0, $testBCompilationTask->getSandboxConfig()->getLimits("groupA")->getWallTime());
  }

}

# Testing methods run
$testCase = new TestBaseCompiler();
$testCase->run();
