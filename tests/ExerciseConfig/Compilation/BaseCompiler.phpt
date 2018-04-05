<?php

include '../../bootstrap.php';

use App\Helpers\EntityMetadata\Solution\SolutionParams;
use App\Helpers\ExerciseConfig\Compilation\BoxesCompiler;
use App\Helpers\ExerciseConfig\Compilation\BoxesOptimizer;
use App\Helpers\ExerciseConfig\Compilation\BoxesSorter;
use App\Helpers\ExerciseConfig\Compilation\BaseCompiler;
use App\Helpers\ExerciseConfig\Compilation\CompilationContext;
use App\Helpers\ExerciseConfig\Compilation\CompilationParams;
use App\Helpers\ExerciseConfig\Compilation\PipelinesMerger;
use App\Helpers\ExerciseConfig\Compilation\TestDirectoriesResolver;
use App\Helpers\ExerciseConfig\Compilation\VariablesResolver;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Helpers\ExerciseConfig\Pipeline\Box\CompilationBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\GccCompilationBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\JudgeBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\LinuxSandbox;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\Priorities;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskCommands;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskType;
use App\Model\Repository\Pipelines;
use Tester\Assert;


/**
 * All special cases should be resolved in appropriate tests. This test is only
 * integration test of all compiler components and if it is working as expected.
 * @testCase
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
      "1" => [
        "environments" => [
          "envA" => [ "pipelines" => [
            [ "name" => "compilationPipeline", "variables" => [] ],
            [ "name" => "testPipeline", "variables" => [
              [ "name" => "input-file", "type" => "remote-file", "value" => "expected.A.in" ]
            ] ]
          ] ],
          "envB" => [ "pipelines" => [
            [ "name" => "compilationPipeline", "variables" => [] ],
            [ "name" => "testPipeline", "variables" => [
              [ "name" => "input-file", "type" => "remote-file", "value" => "expected.A.in" ]
            ] ]
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
              [ "name" => "testPipeline", "variables" => [
                [ "name" => "input-file", "type" => "remote-file", "value" => "expected.B.in" ]
              ] ]
            ]
          ]
        ]
      ]
    ]
  ];
  private static $envVariablesTable = [
    [ "name" => "source_files", "type" => "file[]", "value" => "source" ],
    [ "name" => "expected_output", "type" => "file", "value" => '$expected-a-out' ]
  ];
  private static $environment = "envA";
  private static $compilationPipeline = [
    "variables" => [
      ["name" => "source_files", "type" => "file[]", "value" => ["source"]],
      ["name" => "extra_files", "type" => "file[]", "value" => ["extra"]],
      ["name" => "binary_file", "type" => "file", "value" => "a.out"]
    ],
    "boxes" => [
      [
        "name" => "source",
        "type" => "files-in",
        "portsIn" => [],
        "portsOut" => [
          "input" => ["type" => "file[]", "value" => "source_files"]
        ]
      ],
      [
        "name" => "extra",
        "type" => "files-in",
        "portsIn" => [],
        "portsOut" => [
          "input" => ["type" => "file[]", "value" => "extra_files"]
        ]
      ],
      [
        "name" => "compilation",
        "type" => "gcc",
        "portsIn" => [
          "args" => ["type" => "string[]", "value" => ""],
          "source-files" => ["type" => "file[]", "value" => "source_files"],
          "extra-files" => ["type" => "file[]", "value" => "extra_files"]
        ],
        "portsOut" => [
          "binary-file" => ["type" => "file", "value" => "binary_file"]
        ]
      ],
      [
        "name" => "output",
        "type" => "file-out",
        "portsIn" => [
          "output" => ["type" => "file", "value" => "binary_file"]
        ],
        "portsOut" => []
      ]
    ]
  ];
  private static $testPipeline = [
    "variables" => [
      [ "name" => "binary_file", "type" => "file", "value" => "a.out" ],
      [ "name" => "input-file", "type" => "file", "value" => "" ],
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
        "name" => "input-file",
        "type" => "file-in",
        "portsIn" => [],
        "portsOut" => [
          "input" => [ "type" => "file", "value" => "input-file" ]
        ]
      ],
      [
        "name" => "run",
        "type" => "elf-exec",
        "portsIn" => [
          "args" => [ "type" => "string[]", "value" => "" ],
          "stdin" => [ "type" => "file", "value" => "input-file" ],
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
  private static $limits = [
    [ // groupA
      "1" => [
        "memory" => 123,
        "wall-time" => 456.0
      ]
    ],
    [ // groupB
      "1" => [
        "memory" => 654,
        "cpu-time" => 321.0
      ]
    ]
  ];
  private static $testsNames = [
    "1" => "testA",
    "2" => "testB"
  ];
  private static $pipelineFiles = [];
  private static $exerciseFiles = [
    "expected.A.out" => "expected.A.out.hash",
    "expected.A.in" => "expected.A.in.hash",
    "expected.B.out" => "expected.B.out.hash",
    "expected.B.in" => "expected.B.in.hash"
  ];
  private static $submitFiles = [ "source" ];
  private static $solutionParams = [
    "variables" => [
        ["name" => "expected-a-out", "value" => "source"]
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
    $this->mockCompilationPipeline->shouldReceive("getHashedSupplementaryFiles")->andReturn(self::$pipelineFiles);
    $this->mockTestPipelineConfig = Mockery::mock(\App\Model\Entity\PipelineConfig::class);
    $this->mockTestPipeline = Mockery::mock(\App\Model\Entity\Pipeline::class);
    $this->mockTestPipeline->shouldReceive("getPipelineConfig")->andReturn($this->mockTestPipelineConfig);
    $this->mockTestPipeline->shouldReceive("getHashedSupplementaryFiles")->andReturn(self::$pipelineFiles);

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

    $context = CompilationContext::create($exerciseConfig, $environmentConfigVariables, $limits,
      self::$exerciseFiles, self::$testsNames, self::$environment);
    $params = CompilationParams::create(self::$submitFiles, true, new SolutionParams(self::$solutionParams));
    $jobConfig = $this->compiler->compile($context, $params);

    // check general properties
    Assert::equal(["groupA", "groupB"], $jobConfig->getSubmissionHeader()->getHardwareGroups());
    Assert::equal(16, $jobConfig->getTasksCount());

    ////////////////////////////////////////////////////////////////////////////
    // check order of all tasks and right attributes
    //
    $it = 65536;

    $testAMkdir = $jobConfig->getTasks()[0];
    Assert::equal("testA..mkdir." . $it--, $testAMkdir->getId());
    Assert::equal(Priorities::$DEFAULT, $testAMkdir->getPriority());
    Assert::count(0, $testAMkdir->getDependencies());
    Assert::equal("mkdir", $testAMkdir->getCommandBinary());
    Assert::equal([ConfigParams::$SOURCE_DIR . "testA"], $testAMkdir->getCommandArguments());
    Assert::null($testAMkdir->getType());
    Assert::equal("testA", $testAMkdir->getTestId());
    Assert::null($testAMkdir->getSandboxConfig());

    $dumpTestAMkdir = $jobConfig->getTasks()[1];
    Assert::equal("testA..dump-results." . $it--, $dumpTestAMkdir->getId());
    Assert::equal(Priorities::$DUMP_RESULTS, $dumpTestAMkdir->getPriority());
    Assert::count(1, $dumpTestAMkdir->getDependencies());
    Assert::equal([$testAMkdir->getId()], $dumpTestAMkdir->getDependencies());
    Assert::equal("dumpdir", $dumpTestAMkdir->getCommandBinary());
    Assert::equal([ConfigParams::$SOURCE_DIR . "testA", ConfigParams::$RESULT_DIR . "testA", ConfigParams::$DUMPDIR_LIMIT],
      $dumpTestAMkdir->getCommandArguments());
    Assert::null($dumpTestAMkdir->getType());
    Assert::equal("testA", $dumpTestAMkdir->getTestId());
    Assert::null($dumpTestAMkdir->getSandboxConfig());

    $testBMkdir = $jobConfig->getTasks()[2];
    Assert::equal("testB..mkdir." . $it--, $testBMkdir->getId());
    Assert::equal(Priorities::$DEFAULT, $testBMkdir->getPriority());
    Assert::count(0, $testBMkdir->getDependencies());
    Assert::equal("mkdir", $testBMkdir->getCommandBinary());
    Assert::equal([ConfigParams::$SOURCE_DIR . "testB"], $testBMkdir->getCommandArguments());
    Assert::null($testBMkdir->getType());
    Assert::equal("testB", $testBMkdir->getTestId());
    Assert::null($testBMkdir->getSandboxConfig());

    $dumpTestBMkdir = $jobConfig->getTasks()[3];
    Assert::equal("testB..dump-results." . $it--, $dumpTestBMkdir->getId());
    Assert::equal(Priorities::$DUMP_RESULTS, $dumpTestBMkdir->getPriority());
    Assert::count(1, $dumpTestBMkdir->getDependencies());
    Assert::equal([$testBMkdir->getId()], $dumpTestBMkdir->getDependencies());
    Assert::equal("dumpdir", $dumpTestBMkdir->getCommandBinary());
    Assert::equal([ConfigParams::$SOURCE_DIR . "testB", ConfigParams::$RESULT_DIR . "testB", ConfigParams::$DUMPDIR_LIMIT],
      $dumpTestBMkdir->getCommandArguments());
    Assert::null($dumpTestBMkdir->getType());
    Assert::equal("testB", $dumpTestBMkdir->getTestId());
    Assert::null($dumpTestBMkdir->getSandboxConfig());

    $testAInputTask = $jobConfig->getTasks()[4];
    Assert::equal("testA.testPipeline.input-file." . $it--, $testAInputTask->getId());
    Assert::equal(Priorities::$DEFAULT, $testAInputTask->getPriority());
    Assert::count(1, $testAInputTask->getDependencies());
    Assert::equal([$testAMkdir->getId()], $testAInputTask->getDependencies());
    Assert::equal("fetch", $testAInputTask->getCommandBinary());
    Assert::equal(["expected.A.in.hash", ConfigParams::$SOURCE_DIR . "testA/expected.A.in.hash"], $testAInputTask->getCommandArguments());
    Assert::null($testAInputTask->getType());
    Assert::equal("testA", $testAInputTask->getTestId());
    Assert::null($testAInputTask->getSandboxConfig());

    $testACopyTask = $jobConfig->getTasks()[5];
    Assert::equal("testA.testPipeline.test." . $it--, $testACopyTask->getId());
    Assert::equal(Priorities::$DEFAULT, $testACopyTask->getPriority());
    Assert::count(1, $testACopyTask->getDependencies());
    Assert::equal([$testAMkdir->getId()], $testACopyTask->getDependencies());
    Assert::equal("cp", $testACopyTask->getCommandBinary());
    Assert::equal([ConfigParams::$SOURCE_DIR . "source", ConfigParams::$SOURCE_DIR . "testA/expected.out"], $testACopyTask->getCommandArguments());
    Assert::null($testACopyTask->getType());
    Assert::equal("testA", $testACopyTask->getTestId());
    Assert::null($testACopyTask->getSandboxConfig());

    $testAExtraTask = $jobConfig->getTasks()[6];
    Assert::equal("testA.compilationPipeline.extra." . $it--, $testAExtraTask->getId());
    Assert::equal(Priorities::$DEFAULT, $testAExtraTask->getPriority());
    Assert::count(1, $testAExtraTask->getDependencies());
    Assert::equal([$testAMkdir->getId()], $testAExtraTask->getDependencies());
    Assert::equal("cp", $testAExtraTask->getCommandBinary());
    Assert::equal([ConfigParams::$SOURCE_DIR . "extra", ConfigParams::$SOURCE_DIR . "testA/extra"], $testAExtraTask->getCommandArguments());
    Assert::null($testAExtraTask->getType());
    Assert::equal("testA", $testAExtraTask->getTestId());
    Assert::null($testAExtraTask->getSandboxConfig());

    $testASourceTask = $jobConfig->getTasks()[7];
    Assert::equal("testA.compilationPipeline.source." . $it--, $testASourceTask->getId());
    Assert::equal(Priorities::$DEFAULT, $testASourceTask->getPriority());
    Assert::count(1, $testASourceTask->getDependencies());
    Assert::equal([$testAMkdir->getId()], $testASourceTask->getDependencies());
    Assert::equal("cp", $testASourceTask->getCommandBinary());
    Assert::equal([ConfigParams::$SOURCE_DIR . "source", ConfigParams::$SOURCE_DIR . "testA/source"], $testASourceTask->getCommandArguments());
    Assert::null($testASourceTask->getType());
    Assert::equal("testA", $testASourceTask->getTestId());
    Assert::null($testASourceTask->getSandboxConfig());

    $testACompilationTask = $jobConfig->getTasks()[8];
    Assert::equal("testA.compilationPipeline.compilation." . $it--, $testACompilationTask->getId());
    Assert::equal(Priorities::$INITIATION, $testACompilationTask->getPriority());
    Assert::count(3, $testACompilationTask->getDependencies());
    Assert::equal([$testASourceTask->getId(), $testAExtraTask->getId(), $testAMkdir->getId()], $testACompilationTask->getDependencies());
    Assert::equal(GccCompilationBox::$GCC_BINARY, $testACompilationTask->getCommandBinary());
    Assert::equal([ConfigParams::$EVAL_DIR . "testA/source", ConfigParams::$EVAL_DIR . "testA/extra", "-o", ConfigParams::$EVAL_DIR . "testA/a.out"], $testACompilationTask->getCommandArguments());
    Assert::equal(TaskType::$INITIATION, $testACompilationTask->getType());
    Assert::equal("testA", $testACompilationTask->getTestId());
    Assert::notEqual(null, $testACompilationTask->getSandboxConfig());
    Assert::equal(LinuxSandbox::$ISOLATE, $testACompilationTask->getSandboxConfig()->getName());
    Assert::count(0, $testACompilationTask->getSandboxConfig()->getLimitsArray());

    $testAExistsTask = $jobConfig->getTasks()[9];
    Assert::equal("testA.compilationPipeline.compilation." . $it--, $testAExistsTask->getId());
    Assert::equal(Priorities::$INITIATION, $testAExistsTask->getPriority());
    Assert::count(3, $testAExistsTask->getDependencies());
    Assert::equal([$testASourceTask->getId(), $testAExtraTask->getId(), $testAMkdir->getId()], $testAExistsTask->getDependencies());
    Assert::equal(TaskCommands::$EXISTS, $testAExistsTask->getCommandBinary());
    Assert::equal([CompilationBox::$EXISTS_FAILED_MSG, ConfigParams::$SOURCE_DIR . "testA/a.out"], $testAExistsTask->getCommandArguments());
    Assert::equal(TaskType::$INITIATION, $testAExistsTask->getType());
    Assert::equal("testA", $testAExistsTask->getTestId());
    Assert::null($testAExistsTask->getSandboxConfig());

    $testARunTask = $jobConfig->getTasks()[10];
    Assert::equal("testA.testPipeline.run." . $it--, $testARunTask->getId());
    Assert::equal(Priorities::$EXECUTION, $testARunTask->getPriority());
    Assert::count(4, $testARunTask->getDependencies());
    Assert::equal([$testAInputTask->getId(), $testACompilationTask->getId(),
      $testAExistsTask->getId(), $testAMkdir->getId()], $testARunTask->getDependencies());
    Assert::equal(ConfigParams::$EVAL_DIR . "testA/a.out", $testARunTask->getCommandBinary());
    Assert::equal([], $testARunTask->getCommandArguments());
    Assert::equal(TaskType::$EXECUTION, $testARunTask->getType());
    Assert::equal("testA", $testARunTask->getTestId());
    Assert::notEqual(null, $testARunTask->getSandboxConfig());
    Assert::equal(LinuxSandbox::$ISOLATE, $testARunTask->getSandboxConfig()->getName());
    Assert::null($testARunTask->getSandboxConfig()->getChdir());
    Assert::count(2, $testARunTask->getSandboxConfig()->getLimitsArray());
    Assert::equal(ConfigParams::$EVAL_DIR . "testA/expected.A.in.hash", $testARunTask->getSandboxConfig()->getStdin());
    Assert::contains(".stderr", $testARunTask->getSandboxConfig()->getStderr());
    Assert::contains('${EVAL_DIR}/testA/', $testARunTask->getSandboxConfig()->getStderr());
    Assert::equal(123, $testARunTask->getSandboxConfig()->getLimits("groupA")->getMemoryLimit());
    Assert::equal(456.0, $testARunTask->getSandboxConfig()->getLimits("groupA")->getWallTime());
    Assert::equal(654, $testARunTask->getSandboxConfig()->getLimits("groupB")->getMemoryLimit());
    Assert::equal(321.0, $testARunTask->getSandboxConfig()->getLimits("groupB")->getTimeLimit());

    $testAJudgeTask = $jobConfig->getTasks()[11];
    Assert::equal("testA.testPipeline.judge." . $it--, $testAJudgeTask->getId());
    Assert::equal(Priorities::$EVALUATION, $testAJudgeTask->getPriority());
    Assert::count(3, $testAJudgeTask->getDependencies());
    Assert::equal([$testACopyTask->getId(), $testARunTask->getId(), $testAMkdir->getId()],
      $testAJudgeTask->getDependencies());
    Assert::equal(ConfigParams::$JUDGES_DIR . "recodex-judge-normal", $testAJudgeTask->getCommandBinary());
    Assert::equal([ConfigParams::$EVAL_DIR . "testA/expected.out", ConfigParams::$EVAL_DIR . "testA/actual.out"], $testAJudgeTask->getCommandArguments());
    Assert::equal(TaskType::$EVALUATION, $testAJudgeTask->getType());
    Assert::equal("testA", $testAJudgeTask->getTestId());
    Assert::notEqual(null, $testAJudgeTask->getSandboxConfig());
    Assert::equal(LinuxSandbox::$ISOLATE, $testAJudgeTask->getSandboxConfig()->getName());
    Assert::null($testAJudgeTask->getSandboxConfig()->getChdir());
    Assert::count(0, $testAJudgeTask->getSandboxConfig()->getLimitsArray());

    $testBExtraTask = $jobConfig->getTasks()[12];
    Assert::equal("testB.compilationPipeline.extra." . $it--, $testBExtraTask->getId());
    Assert::equal(Priorities::$DEFAULT, $testBExtraTask->getPriority());
    Assert::count(1, $testBExtraTask->getDependencies());
    Assert::equal([$testBMkdir->getId()], $testBExtraTask->getDependencies());
    Assert::equal(TaskCommands::$COPY, $testBExtraTask->getCommandBinary());
    Assert::equal([ConfigParams::$SOURCE_DIR . "extra", ConfigParams::$SOURCE_DIR . "testB/extra"], $testBExtraTask->getCommandArguments());
    Assert::null($testBExtraTask->getType());
    Assert::equal("testB", $testBExtraTask->getTestId());
    Assert::null($testBExtraTask->getSandboxConfig());

    $testBSourceTask = $jobConfig->getTasks()[13];
    Assert::equal("testB.compilationPipeline.source." . $it--, $testBSourceTask->getId());
    Assert::equal(Priorities::$DEFAULT, $testBSourceTask->getPriority());
    Assert::count(1, $testBSourceTask->getDependencies());
    Assert::equal([$testBMkdir->getId()], $testBSourceTask->getDependencies());
    Assert::equal(TaskCommands::$COPY, $testBSourceTask->getCommandBinary());
    Assert::equal([ConfigParams::$SOURCE_DIR . "source", ConfigParams::$SOURCE_DIR . "testB/source"], $testBSourceTask->getCommandArguments());
    Assert::null($testBSourceTask->getType());
    Assert::equal("testB", $testBSourceTask->getTestId());
    Assert::null($testBSourceTask->getSandboxConfig());

    $testBCompilationTask = $jobConfig->getTasks()[14];
    Assert::equal("testB.compilationPipeline.compilation." . $it--, $testBCompilationTask->getId());
    Assert::equal(Priorities::$INITIATION, $testBCompilationTask->getPriority());
    Assert::count(3, $testBCompilationTask->getDependencies());
    Assert::equal([$testBSourceTask->getId(), $testBExtraTask->getId(), $testBMkdir->getId()], $testBCompilationTask->getDependencies());
    Assert::equal(GccCompilationBox::$GCC_BINARY, $testBCompilationTask->getCommandBinary());
    Assert::equal([ConfigParams::$EVAL_DIR . "testB/source", ConfigParams::$EVAL_DIR . "testB/extra", "-o", ConfigParams::$EVAL_DIR . "testB/a.out"], $testBCompilationTask->getCommandArguments());
    Assert::equal(TaskType::$INITIATION, $testBCompilationTask->getType());
    Assert::equal("testB", $testBCompilationTask->getTestId());
    Assert::notEqual(null, $testBCompilationTask->getSandboxConfig());
    Assert::equal(LinuxSandbox::$ISOLATE, $testBCompilationTask->getSandboxConfig()->getName());
    Assert::count(0, $testBCompilationTask->getSandboxConfig()->getLimitsArray());

    $testBExistsTask = $jobConfig->getTasks()[15];
    Assert::equal("testB.compilationPipeline.compilation." . $it--, $testBExistsTask->getId());
    Assert::equal(Priorities::$INITIATION, $testBExistsTask->getPriority());
    Assert::count(3, $testBExistsTask->getDependencies());
    Assert::equal([$testBSourceTask->getId(), $testBExtraTask->getId(), $testBMkdir->getId()], $testBExistsTask->getDependencies());
    Assert::equal(TaskCommands::$EXISTS, $testBExistsTask->getCommandBinary());
    Assert::equal([CompilationBox::$EXISTS_FAILED_MSG, ConfigParams::$SOURCE_DIR . "testB/a.out"], $testBExistsTask->getCommandArguments());
    Assert::equal(TaskType::$INITIATION, $testBExistsTask->getType());
    Assert::equal("testB", $testBExistsTask->getTestId());
    Assert::null($testBExistsTask->getSandboxConfig());
  }

}

# Testing methods run
$testCase = new TestBaseCompiler();
$testCase->run();
