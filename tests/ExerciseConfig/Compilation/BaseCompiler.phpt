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
use App\Helpers\ExerciseConfig\Compilation\DirectoriesResolver;
use App\Helpers\ExerciseConfig\Compilation\VariablesResolver;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Helpers\ExerciseConfig\Pipeline\Box\CompilationBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\GccCompilationBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\ConfigParams;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\LinuxSandbox;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\Priorities;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskCommands;
use App\Helpers\ExerciseConfig\Pipeline\Box\Params\TaskType;
use App\Helpers\ExerciseConfig\PipelinesCache;
use Nette\Utils\Strings;
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

    /** @var Mockery\Mock | PipelinesCache */
    private $mockPipelinesCache;

    /** @var Mockery\Mock | \App\Model\Entity\Pipeline */
    private $mockCompilationPipeline;
    /** @var Mockery\Mock | \App\Model\Entity\Pipeline */
    private $mockTestPipeline;


    private static $exerciseConfig = [
        "environments" => ["envA", "envB"],
        "tests" => [
            "1" => [
                "environments" => [
                    "envA" => [
                        "pipelines" => [
                            ["name" => "compilationPipeline", "variables" => []],
                            [
                                "name" => "testPipeline",
                                "variables" => [
                                    ["name" => "input-file", "type" => "remote-file", "value" => "expected.A.in"]
                                ]
                            ]
                        ]
                    ],
                    "envB" => [
                        "pipelines" => [
                            ["name" => "compilationPipeline", "variables" => []],
                            [
                                "name" => "testPipeline",
                                "variables" => [
                                    ["name" => "input-file", "type" => "remote-file", "value" => "expected.A.in"]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "2" => [
                "environments" => [
                    "envA" => [
                        "pipelines" => [
                            ["name" => "compilationPipeline", "variables" => []],
                            [
                                "name" => "testPipeline",
                                "variables" => [
                                    ["name" => "input-file", "type" => "remote-file", "value" => "expected.B.in"]
                                ]
                            ]
                        ]
                    ],
                    "envB" => [
                        "pipelines" => [
                            ["name" => "compilationPipeline", "variables" => []],
                            [
                                "name" => "testPipeline",
                                "variables" => [
                                    ["name" => "input-file", "type" => "remote-file", "value" => "expected.B.in"]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ];
    private static $envVariablesTable = [
        ["name" => "source_files", "type" => "file[]", "value" => "source.cpp"],
        ["name" => "expected_output", "type" => "file", "value" => '$expected-a-out']
    ];
    private static $environment = "envA";
    private static $compilationPipeline = [
        "variables" => [
            ["name" => "source_files", "type" => "file[]", "value" => ["source.cpp"]],
            ["name" => "extra_files", "type" => "file[]", "value" => ["extra.cpp"]],
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
            ["name" => "binary_file", "type" => "file", "value" => "a.out"],
            ["name" => "input-file", "type" => "file", "value" => ""],
            ["name" => "expected_output", "type" => "file", "value" => "expected.out"],
            ["name" => "actual_output", "type" => "file", "value" => "actual.out"]
        ],
        "boxes" => [
            [
                "name" => "binary",
                "type" => "file-in",
                "portsIn" => [],
                "portsOut" => [
                    "input" => ["type" => "file", "value" => "binary_file"]
                ]
            ],
            [
                "name" => "test",
                "type" => "file-in",
                "portsIn" => [],
                "portsOut" => [
                    "input" => ["type" => "file", "value" => "expected_output"]
                ]
            ],
            [
                "name" => "input-file",
                "type" => "file-in",
                "portsIn" => [],
                "portsOut" => [
                    "input" => ["type" => "file", "value" => "input-file"]
                ]
            ],
            [
                "name" => "run",
                "type" => "elf-exec",
                "portsIn" => [
                    "args" => ["type" => "string[]", "value" => ""],
                    "stdin" => ["type" => "file", "value" => "input-file"],
                    "binary-file" => ["type" => "file", "value" => "binary_file"],
                    "input-files" => ["type" => "file[]", "value" => ""]
                ],
                "portsOut" => [
                    "stdout" => ["type" => "file", "value" => ""],
                    "output-file" => ["type" => "file", "value" => "actual_output"]
                ]
            ],
            [
                "name" => "judge",
                "type" => "judge",
                "portsIn" => [
                    "judge-type" => ["type" => "string", "value" => ""],
                    "args" => ['type' => 'string[]', 'value' => ""],
                    "custom-judge" => ['type' => 'file', 'value' => ""],
                    "actual-output" => ["type" => "file", "value" => "actual_output"],
                    "expected-output" => ["type" => "file", "value" => "expected_output"]
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
    private static $submitFiles = ["source.cpp"];
    private static $solutionParams = [
        "variables" => [
            ["name" => "expected-a-out", "value" => "source.cpp"]
        ]
    ];


    /**
     * TestExerciseConfigCompiler constructor.
     */
    public function __construct()
    {
        // constructions of compiler components
        $this->loader = new Loader(new BoxService());
        $variablesResolver = new VariablesResolver();

        // mock entities and stuff
        $this->mockCompilationPipeline = Mockery::mock(\App\Model\Entity\Pipeline::class);
        $this->mockCompilationPipeline->shouldReceive("getHashedSupplementaryFiles")->andReturn(self::$pipelineFiles);
        $this->mockTestPipeline = Mockery::mock(\App\Model\Entity\Pipeline::class);
        $this->mockTestPipeline->shouldReceive("getHashedSupplementaryFiles")->andReturn(self::$pipelineFiles);

        $this->mockPipelinesCache = Mockery::mock(PipelinesCache::class);
        $this->mockPipelinesCache->shouldReceive("getPipeline")->with("compilationPipeline")->andReturn(
            $this->mockCompilationPipeline
        );
        $this->mockPipelinesCache->shouldReceive("getPipeline")->with("testPipeline")->andReturn(
            $this->mockTestPipeline
        );
        $this->mockPipelinesCache->shouldReceive("getNewPipelineConfig")->with("compilationPipeline")->twice()
            ->andReturn(
                $this->loader->loadPipeline(self::$compilationPipeline),
                $this->loader->loadPipeline(self::$compilationPipeline)
            );
        $this->mockPipelinesCache->shouldReceive("getNewPipelineConfig")->with("testPipeline")->twice()
            ->andReturn(
                $this->loader->loadPipeline(self::$testPipeline),
                $this->loader->loadPipeline(self::$testPipeline)
            );

        // constructions of compiler components
        $pipelinesMerger = new PipelinesMerger($this->mockPipelinesCache, $variablesResolver);
        $boxesSorter = new BoxesSorter();
        $boxesOptimizer = new BoxesOptimizer();
        $boxesCompiler = new BoxesCompiler();
        $directoriesResolver = new DirectoriesResolver();
        $this->compiler = new BaseCompiler(
            $pipelinesMerger,
            $boxesSorter,
            $boxesOptimizer,
            $boxesCompiler,
            $directoriesResolver
        );
    }

    protected function tearDown()
    {
        Mockery::close();
    }


    public function testCorrect()
    {
        $exerciseConfig = $this->loader->loadExerciseConfig(self::$exerciseConfig);
        $environmentConfigVariables = $this->loader->loadVariablesTable(self::$envVariablesTable);
        $limits = [
            "groupA" => $this->loader->loadExerciseLimits(self::$limits[0]),
            "groupB" => $this->loader->loadExerciseLimits(self::$limits[1])
        ];

        $context = CompilationContext::create(
            $exerciseConfig,
            $environmentConfigVariables,
            $limits,
            self::$exerciseFiles,
            self::$testsNames,
            self::$environment
        );
        $params = CompilationParams::create(self::$submitFiles, true, new SolutionParams(self::$solutionParams));
        $jobConfig = $this->compiler->compile($context, $params);

        // check general properties
        Assert::equal(["groupA", "groupB"], $jobConfig->getSubmissionHeader()->getHardwareGroups());
        Assert::equal(20, $jobConfig->getTasksCount());

        /*
     * Accessors
     */
        // check order of all tasks and right attributes
        //
        $it = 0;

        $initiationMkdir = $jobConfig->getTasks()[0];
        Assert::true(Strings::startsWith($initiationMkdir->getId(), "initiation_"));
        Assert::true(Strings::endsWith($initiationMkdir->getId(), "..mkdir." . $it++));
        Assert::equal(Priorities::$DEFAULT, $initiationMkdir->getPriority());
        Assert::count(0, $initiationMkdir->getDependencies());
        Assert::equal("mkdir", $initiationMkdir->getCommandBinary());
        Assert::count(1, $initiationMkdir->getCommandArguments());
        Assert::true(
            Strings::startsWith($initiationMkdir->getCommandArguments()[0], ConfigParams::$SOURCE_DIR . "initiation_")
        );
        Assert::null($initiationMkdir->getType());
        Assert::equal(null, $initiationMkdir->getTestId());
        Assert::null($initiationMkdir->getSandboxConfig());

        $initiationDir = Strings::substring(
            $initiationMkdir->getCommandArguments()[0],
            Strings::length(ConfigParams::$SOURCE_DIR)
        );

        $dumpInitiationMkdir = $jobConfig->getTasks()[1];
        Assert::equal($initiationDir . "..dump-results." . $it++, $dumpInitiationMkdir->getId());
        Assert::equal(Priorities::$DUMP_RESULTS, $dumpInitiationMkdir->getPriority());
        Assert::count(1, $dumpInitiationMkdir->getDependencies());
        Assert::equal([$initiationMkdir->getId()], $dumpInitiationMkdir->getDependencies());
        Assert::equal("dumpdir", $dumpInitiationMkdir->getCommandBinary());
        Assert::equal(
            [
                ConfigParams::$SOURCE_DIR . $initiationDir,
                ConfigParams::$RESULT_DIR . $initiationDir,
                ConfigParams::$DUMPDIR_LIMIT
            ],
            $dumpInitiationMkdir->getCommandArguments()
        );
        Assert::null($dumpInitiationMkdir->getType());
        Assert::equal(null, $dumpInitiationMkdir->getTestId());
        Assert::null($dumpInitiationMkdir->getSandboxConfig());

        $testBMkdir = $jobConfig->getTasks()[2];
        Assert::equal("testB..mkdir." . $it++, $testBMkdir->getId());
        Assert::equal(Priorities::$DEFAULT, $testBMkdir->getPriority());
        Assert::count(0, $testBMkdir->getDependencies());
        Assert::equal("mkdir", $testBMkdir->getCommandBinary());
        Assert::equal([ConfigParams::$SOURCE_DIR . "testB"], $testBMkdir->getCommandArguments());
        Assert::null($testBMkdir->getType());
        Assert::equal(null, $testBMkdir->getTestId());
        Assert::null($testBMkdir->getSandboxConfig());

        $dumpTestBMkdir = $jobConfig->getTasks()[3];
        Assert::equal("testB..dump-results." . $it++, $dumpTestBMkdir->getId());
        Assert::equal(Priorities::$DUMP_RESULTS, $dumpTestBMkdir->getPriority());
        Assert::count(1, $dumpTestBMkdir->getDependencies());
        Assert::equal([$testBMkdir->getId()], $dumpTestBMkdir->getDependencies());
        Assert::equal("dumpdir", $dumpTestBMkdir->getCommandBinary());
        Assert::equal(
            [ConfigParams::$SOURCE_DIR . "testB", ConfigParams::$RESULT_DIR . "testB", ConfigParams::$DUMPDIR_LIMIT],
            $dumpTestBMkdir->getCommandArguments()
        );
        Assert::null($dumpTestBMkdir->getType());
        Assert::equal(null, $dumpTestBMkdir->getTestId());
        Assert::null($dumpTestBMkdir->getSandboxConfig());

        $testAMkdir = $jobConfig->getTasks()[4];
        Assert::equal("testA..mkdir." . $it++, $testAMkdir->getId());
        Assert::equal(Priorities::$DEFAULT, $testAMkdir->getPriority());
        Assert::count(0, $testAMkdir->getDependencies());
        Assert::equal("mkdir", $testAMkdir->getCommandBinary());
        Assert::equal([ConfigParams::$SOURCE_DIR . "testA"], $testAMkdir->getCommandArguments());
        Assert::null($testAMkdir->getType());
        Assert::equal(null, $testAMkdir->getTestId());
        Assert::null($testAMkdir->getSandboxConfig());

        $dumpTestAMkdir = $jobConfig->getTasks()[5];
        Assert::equal("testA..dump-results." . $it++, $dumpTestAMkdir->getId());
        Assert::equal(Priorities::$DUMP_RESULTS, $dumpTestAMkdir->getPriority());
        Assert::count(1, $dumpTestAMkdir->getDependencies());
        Assert::equal([$testAMkdir->getId()], $dumpTestAMkdir->getDependencies());
        Assert::equal("dumpdir", $dumpTestAMkdir->getCommandBinary());
        Assert::equal(
            [ConfigParams::$SOURCE_DIR . "testA", ConfigParams::$RESULT_DIR . "testA", ConfigParams::$DUMPDIR_LIMIT],
            $dumpTestAMkdir->getCommandArguments()
        );
        Assert::null($dumpTestAMkdir->getType());
        Assert::equal(null, $dumpTestAMkdir->getTestId());
        Assert::null($dumpTestAMkdir->getSandboxConfig());

        $initiationExtraTask = $jobConfig->getTasks()[6];
        Assert::equal($initiationDir . ".compilationPipeline.extra." . $it++, $initiationExtraTask->getId());
        Assert::equal(Priorities::$DEFAULT, $initiationExtraTask->getPriority());
        Assert::count(1, $initiationExtraTask->getDependencies());
        Assert::equal([$initiationMkdir->getId()], $initiationExtraTask->getDependencies());
        Assert::equal("cp", $initiationExtraTask->getCommandBinary());
        Assert::equal(
            [ConfigParams::$SOURCE_DIR . "extra.cpp", ConfigParams::$SOURCE_DIR . $initiationDir . "/extra.cpp"],
            $initiationExtraTask->getCommandArguments()
        );
        Assert::null($initiationExtraTask->getType());
        Assert::equal(null, $initiationExtraTask->getTestId());
        Assert::null($initiationExtraTask->getSandboxConfig());

        $initiationSourceTask = $jobConfig->getTasks()[7];
        Assert::equal($initiationDir . ".compilationPipeline.source." . $it++, $initiationSourceTask->getId());
        Assert::equal(Priorities::$DEFAULT, $initiationSourceTask->getPriority());
        Assert::count(1, $initiationSourceTask->getDependencies());
        Assert::equal([$initiationMkdir->getId()], $initiationSourceTask->getDependencies());
        Assert::equal("cp", $initiationSourceTask->getCommandBinary());
        Assert::equal(
            [ConfigParams::$SOURCE_DIR . "source.cpp", ConfigParams::$SOURCE_DIR . $initiationDir . "/source.cpp"],
            $initiationSourceTask->getCommandArguments()
        );
        Assert::null($initiationSourceTask->getType());
        Assert::equal(null, $initiationSourceTask->getTestId());
        Assert::null($initiationSourceTask->getSandboxConfig());

        $initiationCompilationTask = $jobConfig->getTasks()[8];
        Assert::equal(
            $initiationDir . ".compilationPipeline.compilation." . $it++,
            $initiationCompilationTask->getId()
        );
        Assert::equal(Priorities::$INITIATION, $initiationCompilationTask->getPriority());
        Assert::count(3, $initiationCompilationTask->getDependencies());
        Assert::equal(
            [$initiationSourceTask->getId(), $initiationExtraTask->getId(), $initiationMkdir->getId()],
            $initiationCompilationTask->getDependencies()
        );
        Assert::equal(GccCompilationBox::$GCC_BINARY, $initiationCompilationTask->getCommandBinary());
        Assert::equal(
            [
                ConfigParams::$EVAL_DIR . "source.cpp",
                ConfigParams::$EVAL_DIR . "extra.cpp",
                "-o",
                ConfigParams::$EVAL_DIR . "a.out"
            ],
            $initiationCompilationTask->getCommandArguments()
        );
        Assert::equal(TaskType::$INITIATION, $initiationCompilationTask->getType());
        Assert::equal(null, $initiationCompilationTask->getTestId());
        Assert::notEqual(null, $initiationCompilationTask->getSandboxConfig());
        Assert::equal(LinuxSandbox::$ISOLATE, $initiationCompilationTask->getSandboxConfig()->getName());
        Assert::true($initiationCompilationTask->getSandboxConfig()->getStderrToStdout());
        Assert::contains(".out", $initiationCompilationTask->getSandboxConfig()->getCarboncopyStdout());
        Assert::contains(
            '${RESULT_DIR}/compilation.',
            $initiationCompilationTask->getSandboxConfig()->getCarboncopyStdout()
        );
        Assert::equal($initiationDir, $initiationCompilationTask->getSandboxConfig()->getWorkingDirectory());
        Assert::count(0, $initiationCompilationTask->getSandboxConfig()->getLimitsArray());

        $initiationExistsTask = $jobConfig->getTasks()[9];
        Assert::equal($initiationDir . ".compilationPipeline.compilation." . $it++, $initiationExistsTask->getId());
        Assert::equal(Priorities::$INITIATION, $initiationExistsTask->getPriority());
        Assert::count(3, $initiationExistsTask->getDependencies());
        Assert::equal(
            [$initiationSourceTask->getId(), $initiationExtraTask->getId(), $initiationMkdir->getId()],
            $initiationExistsTask->getDependencies()
        );
        Assert::equal(TaskCommands::$EXISTS, $initiationExistsTask->getCommandBinary());
        Assert::equal(
            [CompilationBox::$EXISTS_FAILED_MSG, ConfigParams::$SOURCE_DIR . $initiationDir . "/a.out"],
            $initiationExistsTask->getCommandArguments()
        );
        Assert::equal(TaskType::$INITIATION, $initiationExistsTask->getType());
        Assert::equal(null, $initiationExistsTask->getTestId());
        Assert::null($initiationExistsTask->getSandboxConfig());

        $testAInputTask = $jobConfig->getTasks()[10];
        Assert::equal("testA.testPipeline.input-file." . $it++, $testAInputTask->getId());
        Assert::equal(Priorities::$DEFAULT, $testAInputTask->getPriority());
        Assert::count(1, $testAInputTask->getDependencies());
        Assert::equal([$testAMkdir->getId()], $testAInputTask->getDependencies());
        Assert::equal("fetch", $testAInputTask->getCommandBinary());
        Assert::equal(
            ["expected.A.in.hash", ConfigParams::$SOURCE_DIR . "testA/expected.A.in.hash"],
            $testAInputTask->getCommandArguments()
        );
        Assert::null($testAInputTask->getType());
        Assert::equal("testA", $testAInputTask->getTestId());
        Assert::null($testAInputTask->getSandboxConfig());

        $testACopyTask = $jobConfig->getTasks()[11];
        Assert::equal("testA..copy-file." . $it++, $testACopyTask->getId());
        Assert::equal(Priorities::$DEFAULT, $testACopyTask->getPriority());
        Assert::count(5, $testACopyTask->getDependencies());
        Assert::equal(
            [
                $testAInputTask->getId(),
                $initiationCompilationTask->getId(),
                $initiationExistsTask->getId(),
                $initiationMkdir->getId(),
                $testAMkdir->getId()
            ],
            $testACopyTask->getDependencies()
        );
        Assert::equal("cp", $testACopyTask->getCommandBinary());
        Assert::equal(
            [ConfigParams::$SOURCE_DIR . $initiationDir . "/a.out", ConfigParams::$SOURCE_DIR . "testA"],
            $testACopyTask->getCommandArguments()
        );
        Assert::null($testACopyTask->getType());
        Assert::equal(null, $testACopyTask->getTestId());
        Assert::null($testACopyTask->getSandboxConfig());

        $testARunTask = $jobConfig->getTasks()[12];
        Assert::equal("testA.testPipeline.run." . $it++, $testARunTask->getId());
        Assert::equal(Priorities::$EXECUTION, $testARunTask->getPriority());
        Assert::count(6, $testARunTask->getDependencies());
        Assert::equal(
            [
                $testAInputTask->getId(),
                $initiationCompilationTask->getId(),
                $initiationExistsTask->getId(),
                $initiationMkdir->getId(),
                $testAMkdir->getId(),
                $testACopyTask->getId()
            ],
            $testARunTask->getDependencies()
        );
        Assert::equal(ConfigParams::$EVAL_DIR . "a.out", $testARunTask->getCommandBinary());
        Assert::equal([], $testARunTask->getCommandArguments());
        Assert::equal(TaskType::$EXECUTION, $testARunTask->getType());
        Assert::equal("testA", $testARunTask->getTestId());
        Assert::notEqual(null, $testARunTask->getSandboxConfig());
        Assert::equal(LinuxSandbox::$ISOLATE, $testARunTask->getSandboxConfig()->getName());
        Assert::null($testARunTask->getSandboxConfig()->getChdir());
        Assert::count(2, $testARunTask->getSandboxConfig()->getLimitsArray());
        Assert::equal(ConfigParams::$EVAL_DIR . "expected.A.in.hash", $testARunTask->getSandboxConfig()->getStdin());
        Assert::contains(".stderr", $testARunTask->getSandboxConfig()->getStderr());
        Assert::contains('${EVAL_DIR}/', $testARunTask->getSandboxConfig()->getStderr());
        Assert::equal("testA", $testARunTask->getSandboxConfig()->getWorkingDirectory());
        Assert::equal(123, $testARunTask->getSandboxConfig()->getLimits("groupA")->getMemoryLimit());
        Assert::equal(456.0, $testARunTask->getSandboxConfig()->getLimits("groupA")->getWallTime());
        Assert::equal(654, $testARunTask->getSandboxConfig()->getLimits("groupB")->getMemoryLimit());
        Assert::equal(321.0, $testARunTask->getSandboxConfig()->getLimits("groupB")->getTimeLimit());

        $testATestTask = $jobConfig->getTasks()[13];
        Assert::equal("testA.testPipeline.test." . $it++, $testATestTask->getId());
        Assert::equal(Priorities::$DEFAULT, $testATestTask->getPriority());
        Assert::count(1, $testATestTask->getDependencies());
        Assert::equal([$testAMkdir->getId()], $testATestTask->getDependencies());
        Assert::equal("cp", $testATestTask->getCommandBinary());
        Assert::equal(
            [ConfigParams::$SOURCE_DIR . "source.cpp", ConfigParams::$SOURCE_DIR . "testA/expected.out"],
            $testATestTask->getCommandArguments()
        );
        Assert::null($testATestTask->getType());
        Assert::equal("testA", $testATestTask->getTestId());
        Assert::null($testATestTask->getSandboxConfig());

        $testAJudgeTask = $jobConfig->getTasks()[14];
        Assert::equal("testA.testPipeline.judge." . $it++, $testAJudgeTask->getId());
        Assert::equal(Priorities::$EVALUATION, $testAJudgeTask->getPriority());
        Assert::count(3, $testAJudgeTask->getDependencies());
        Assert::equal(
            [$testATestTask->getId(), $testARunTask->getId(), $testAMkdir->getId()],
            $testAJudgeTask->getDependencies()
        );
        Assert::equal(ConfigParams::$JUDGES_DIR . "recodex-token-judge", $testAJudgeTask->getCommandBinary());
        Assert::equal(
            [
                "--log-limit",
                "4k",
                "--ignore-trailing-whitespace",
                ConfigParams::$EVAL_DIR . "expected.out",
                ConfigParams::$EVAL_DIR . "actual.out"
            ],
            $testAJudgeTask->getCommandArguments()
        );
        Assert::equal(TaskType::$EVALUATION, $testAJudgeTask->getType());
        Assert::equal("testA", $testAJudgeTask->getTestId());
        Assert::notEqual(null, $testAJudgeTask->getSandboxConfig());
        Assert::equal(LinuxSandbox::$ISOLATE, $testAJudgeTask->getSandboxConfig()->getName());
        Assert::equal("testA", $testAJudgeTask->getSandboxConfig()->getWorkingDirectory());
        Assert::count(0, $testAJudgeTask->getSandboxConfig()->getLimitsArray());

        $testBInputTask = $jobConfig->getTasks()[15];
        Assert::equal("testB.testPipeline.input-file." . $it++, $testBInputTask->getId());
        Assert::equal(Priorities::$DEFAULT, $testBInputTask->getPriority());
        Assert::count(1, $testBInputTask->getDependencies());
        Assert::equal([$testBMkdir->getId()], $testBInputTask->getDependencies());
        Assert::equal("fetch", $testBInputTask->getCommandBinary());
        Assert::equal(
            ["expected.B.in.hash", ConfigParams::$SOURCE_DIR . "testB/expected.B.in.hash"],
            $testBInputTask->getCommandArguments()
        );
        Assert::null($testBInputTask->getType());
        Assert::equal("testB", $testBInputTask->getTestId());
        Assert::null($testBInputTask->getSandboxConfig());

        $testBCopyTask = $jobConfig->getTasks()[16];
        Assert::equal("testB..copy-file." . $it++, $testBCopyTask->getId());
        Assert::equal(Priorities::$DEFAULT, $testBCopyTask->getPriority());
        Assert::count(5, $testBCopyTask->getDependencies());
        Assert::equal(
            [
                $testBInputTask->getId(),
                $initiationCompilationTask->getId(),
                $initiationExistsTask->getId(),
                $initiationMkdir->getId(),
                $testBMkdir->getId()
            ],
            $testBCopyTask->getDependencies()
        );
        Assert::equal("cp", $testBCopyTask->getCommandBinary());
        Assert::equal(
            [ConfigParams::$SOURCE_DIR . $initiationDir . "/a.out", ConfigParams::$SOURCE_DIR . "testB"],
            $testBCopyTask->getCommandArguments()
        );
        Assert::null($testBCopyTask->getType());
        Assert::equal(null, $testBCopyTask->getTestId());
        Assert::null($testBCopyTask->getSandboxConfig());

        $testBRunTask = $jobConfig->getTasks()[17];
        Assert::equal("testB.testPipeline.run." . $it++, $testBRunTask->getId());
        Assert::equal(Priorities::$EXECUTION, $testBRunTask->getPriority());
        Assert::count(6, $testBRunTask->getDependencies());
        Assert::equal(
            [
                $testBInputTask->getId(),
                $initiationCompilationTask->getId(),
                $initiationExistsTask->getId(),
                $initiationMkdir->getId(),
                $testBMkdir->getId(),
                $testBCopyTask->getId()
            ],
            $testBRunTask->getDependencies()
        );
        Assert::equal(ConfigParams::$EVAL_DIR . "a.out", $testBRunTask->getCommandBinary());
        Assert::equal([], $testBRunTask->getCommandArguments());
        Assert::equal(TaskType::$EXECUTION, $testBRunTask->getType());
        Assert::equal("testB", $testBRunTask->getTestId());
        Assert::notEqual(null, $testBRunTask->getSandboxConfig());
        Assert::equal(LinuxSandbox::$ISOLATE, $testBRunTask->getSandboxConfig()->getName());
        Assert::null($testBRunTask->getSandboxConfig()->getChdir());
        Assert::count(0, $testBRunTask->getSandboxConfig()->getLimitsArray());
        Assert::equal(ConfigParams::$EVAL_DIR . "expected.B.in.hash", $testBRunTask->getSandboxConfig()->getStdin());
        Assert::contains(".stderr", $testBRunTask->getSandboxConfig()->getStderr());
        Assert::contains('${EVAL_DIR}/', $testBRunTask->getSandboxConfig()->getStderr());
        Assert::equal("testB", $testBRunTask->getSandboxConfig()->getWorkingDirectory());

        $testBTestTask = $jobConfig->getTasks()[18];
        Assert::equal("testB.testPipeline.test." . $it++, $testBTestTask->getId());
        Assert::equal(Priorities::$DEFAULT, $testBTestTask->getPriority());
        Assert::count(1, $testBTestTask->getDependencies());
        Assert::equal([$testBMkdir->getId()], $testBTestTask->getDependencies());
        Assert::equal("cp", $testBTestTask->getCommandBinary());
        Assert::equal(
            [ConfigParams::$SOURCE_DIR . "source.cpp", ConfigParams::$SOURCE_DIR . "testB/expected.out"],
            $testBTestTask->getCommandArguments()
        );
        Assert::null($testBTestTask->getType());
        Assert::equal("testB", $testBTestTask->getTestId());
        Assert::null($testBTestTask->getSandboxConfig());

        $testBJudgeTask = $jobConfig->getTasks()[19];
        Assert::equal("testB.testPipeline.judge." . $it++, $testBJudgeTask->getId());
        Assert::equal(Priorities::$EVALUATION, $testBJudgeTask->getPriority());
        Assert::count(3, $testBJudgeTask->getDependencies());
        Assert::equal(
            [$testBTestTask->getId(), $testBRunTask->getId(), $testBMkdir->getId()],
            $testBJudgeTask->getDependencies()
        );
        Assert::equal(ConfigParams::$JUDGES_DIR . "recodex-token-judge", $testBJudgeTask->getCommandBinary());
        Assert::equal(
            [
                "--log-limit",
                "4k",
                "--ignore-trailing-whitespace",
                ConfigParams::$EVAL_DIR . "expected.out",
                ConfigParams::$EVAL_DIR . "actual.out"
            ],
            $testBJudgeTask->getCommandArguments()
        );
        Assert::equal(TaskType::$EVALUATION, $testBJudgeTask->getType());
        Assert::equal("testB", $testBJudgeTask->getTestId());
        Assert::notEqual(null, $testBJudgeTask->getSandboxConfig());
        Assert::equal(LinuxSandbox::$ISOLATE, $testBJudgeTask->getSandboxConfig()->getName());
        Assert::equal("testB", $testBJudgeTask->getSandboxConfig()->getWorkingDirectory());
        Assert::count(0, $testBJudgeTask->getSandboxConfig()->getLimitsArray());
    }
}

# Testing methods run
$testCase = new TestBaseCompiler();
$testCase->run();
