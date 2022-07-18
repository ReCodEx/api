<?php

include '../bootstrap.php';

use App\Helpers\Evaluation\IExercise;
use App\Helpers\ExerciseConfig\Helper;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Helpers\ExerciseConfig\PipelinesCache;
use App\Helpers\ExerciseConfig\VariablesTable;
use App\Helpers\ExerciseConfig\VariableTypes;
use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseConfig;
use App\Model\Entity\ExerciseEnvironmentConfig;
use App\Model\Entity\Group;
use App\Model\Entity\RuntimeEnvironment;
use App\Model\Entity\User;
use App\Model\Entity\Instance;
use Mockery\Mock;
use App\Helpers\Yaml;
use Tester\Assert;


/**
 * @testCase
 */
class TestExerciseConfigHelper extends Tester\TestCase
{
    /** @var Helper */
    private $helper;

    /** @var Loader */
    private $loader;

    /** @var PipelinesCache|Mock */
    private $pipelinesCache;

    public function __construct()
    {
        $this->loader = new Loader(new BoxService());

        $this->pipelinesCache = Mockery::mock(PipelinesCache::class);
        $this->pipelinesCache->shouldReceive("getPipelineConfig")->with("2341b599-c388-4357-8fea-be1e3bb182e0")
            ->andReturn($this->loader->loadPipeline(self::$compilationPipeline));
        $this->pipelinesCache->shouldReceive("getPipelineConfig")->with("9a511efd-fd36-43ce-aa45-e2721845ae3b")
            ->andReturn($this->loader->loadPipeline(self::$testPipeline));

        $this->helper = new Helper($this->loader, $this->pipelinesCache);
    }


    private static $cEnvVariablesTable = [
        ["name" => "source_files", "type" => "file[]", "value" => "*.c"],
        ["name" => "submit_file", "type" => "file", "value" => '$c-submit-file']
    ];
    private static $javaEnvVariablesTable = [
        ["name" => "source_files", "type" => "file[]", "value" => "*.java"],
        ["name" => "submit_file", "type" => "file", "value" => '$java-submit-file']
    ];
    private static $exerciseConfig = [
        "environments" => ["c-gcc-linux", "java-linux"],
        "tests" => [
            "1" => [
                "environments" => [
                    "c-gcc-linux" => [
                        "pipelines" => [
                            [
                                "name" => "2341b599-c388-4357-8fea-be1e3bb182e0",
                                "variables" => [
                                    [
                                        "name" => "submit_exercise_file",
                                        "type" => "file",
                                        "value" => '$c-submit-exercise-file'
                                    ],
                                ]
                            ],
                            [
                                "name" => "9a511efd-fd36-43ce-aa45-e2721845ae3b",
                                "variables" => [
                                    ["name" => "expected_output", "type" => "remote-file", "value" => "expected.A.out"],
                                    ["name" => "input-file", "type" => "remote-file", "value" => "expected.A.in"]
                                ]
                            ]
                        ]
                    ],
                    "java-linux" => [
                        "pipelines" => [
                            [
                                "name" => "2341b599-c388-4357-8fea-be1e3bb182e0",
                                "variables" => [
                                    [
                                        "name" => "submit_exercise_file",
                                        "type" => "file",
                                        "value" => '$java-submit-exercise-file'
                                    ],
                                ]
                            ],
                            [
                                "name" => "9a511efd-fd36-43ce-aa45-e2721845ae3b",
                                "variables" => [
                                    ["name" => "expected_output", "type" => "remote-file", "value" => "expected.A.out"],
                                    ["name" => "input-file", "type" => "remote-file", "value" => "expected.A.in"]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "2" => [
                "environments" => [
                    "c-gcc-linux" => [
                        "pipelines" => [
                            ["name" => "2341b599-c388-4357-8fea-be1e3bb182e0", "variables" => []],
                        ]
                    ],
                    "java-linux" => [
                        "pipelines" => [
                            [
                                "name" => "2341b599-c388-4357-8fea-be1e3bb182e0",
                                "variables" => [
                                    [
                                        "name" => "submit_exercise_file",
                                        "type" => "file",
                                        "value" => '$java-submit-exercise-file'
                                    ],
                                ]
                            ],
                            [
                                "name" => "9a511efd-fd36-43ce-aa45-e2721845ae3b",
                                "variables" => [
                                    ["name" => "expected_output", "type" => "remote-file", "value" => "expected.B.out"],
                                    ["name" => "input-file", "type" => "remote-file", "value" => "expected.B.in"]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ];
    private static $compilationPipeline = [
        "variables" => [
            ["name" => "source_files", "type" => "file[]", "value" => ["source"]],
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
                "name" => "compilation",
                "type" => "gcc",
                "portsIn" => [
                    "args" => ["type" => "string[]", "value" => ""],
                    "source-files" => ["type" => "file[]", "value" => "source_files"],
                    "extra-files" => ["type" => "file[]", "value" => ""]
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


    public function testVariablesForExerciseEmptyArray()
    {
        $result = $this->helper->getVariablesForExercise([], new VariablesTable());
        Assert::equal([], $result);
    }

    public function testVariablesForExerciseSimple()
    {
        $pipelineId = "pipeline";
        $pipeline = $this->loader->loadPipeline(
            [
                "variables" => [],
                "boxes" => [
                    [
                        "name" => "file",
                        "type" => "file-in",
                        "portsIn" => [],
                        "portsOut" => ["input" => ['type' => 'file', 'value' => "data-in"]]
                    ]
                ]
            ]
        );

        $this->pipelinesCache->shouldReceive("getPipelineConfig")->with($pipelineId)->andReturn($pipeline);

        $result = $this->helper->getVariablesForExercise([$pipelineId], new VariablesTable());
        Assert::count(1, $result);
        Assert::equal($pipelineId, $result[0]["id"]);
        Assert::count(1, $result[0]["variables"]);

        Assert::equal("data-in", $result[0]["variables"][0]->getName());
        Assert::equal(VariableTypes::$REMOTE_FILE_TYPE, $result[0]["variables"][0]->getType());
        Assert::equal("", $result[0]["variables"][0]->getValue());
    }

    public function testVariablesForExerciseEmptyAfterJoin()
    {
        $pipelineAid = "pipelineA";
        $pipelineA = $this->loader->loadPipeline(
            [
                "variables" => [],
                "boxes" => [
                    [
                        "name" => "file",
                        "type" => "file-out",
                        "portsIn" => ["output" => ['type' => 'file', 'value' => "join"]],
                        "portsOut" => []
                    ]
                ]
            ]
        );
        $pipelineBid = "pipelineB";
        $pipelineB = $this->loader->loadPipeline(
            [
                "variables" => [],
                "boxes" => [
                    [
                        "name" => "file",
                        "type" => "file-in",
                        "portsIn" => [],
                        "portsOut" => ["input" => ['type' => 'file', 'value' => "join"]]
                    ]
                ]
            ]
        );

        $this->pipelinesCache->shouldReceive("getPipelineConfig")->with($pipelineAid)->andReturn($pipelineA);
        $this->pipelinesCache->shouldReceive("getPipelineConfig")->with($pipelineBid)->andReturn($pipelineB);

        $result = $this->helper->getVariablesForExercise([$pipelineAid, $pipelineBid], new VariablesTable());
        Assert::count(2, $result);
        Assert::equal($pipelineAid, $result[0]["id"]);
        Assert::equal($pipelineBid, $result[1]["id"]);

        Assert::count(0, $result[0]["variables"]);
        Assert::count(0, $result[1]["variables"]);
    }

    public function testVariablesForExerciseReferences()
    {
        $pipelineAid = "pipelineA";
        $pipelineA = $this->loader->loadPipeline(
            [
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
                        "type" => "file-out",
                        "portsIn" => ["output" => ['type' => 'file', 'value' => "join"]],
                        "portsOut" => []
                    ],
                    [
                        "name" => "judge",
                        "type" => "judge",
                        "portsIn" => [
                            "judge-type" => ['type' => 'string', 'value' => ""],
                            "args" => ['type' => 'string[]', 'value' => ""],
                            "custom-judge" => ['type' => 'file', 'value' => ""],
                            "actual-output" => ['type' => 'file', 'value' => "actual"],
                            "expected-output" => ['type' => 'file', 'value' => "expected"]
                        ],
                        "portsOut" => []
                    ]
                ]
            ]
        );
        $pipelineBid = "pipelineB";
        $pipelineB = $this->loader->loadPipeline(
            [
                "variables" => [],
                "boxes" => [
                    [
                        "name" => "file",
                        "type" => "file-in",
                        "portsIn" => [],
                        "portsOut" => ["input" => ['type' => 'file', 'value' => "join"]]
                    ]
                ]
            ]
        );

        $this->pipelinesCache->shouldReceive("getPipelineConfig")->with($pipelineAid)->andReturn($pipelineA);
        $this->pipelinesCache->shouldReceive("getPipelineConfig")->with($pipelineBid)->andReturn($pipelineB);

        $result = $this->helper->getVariablesForExercise([$pipelineAid, $pipelineBid], new VariablesTable());
        Assert::count(2, $result);
        Assert::equal($pipelineAid, $result[0]["id"]);
        Assert::equal($pipelineBid, $result[1]["id"]);

        Assert::count(2, $result[0]["variables"]);
        Assert::count(0, $result[1]["variables"]);

        Assert::equal("actualFile", $result[0]["variables"][0]->getName());
        Assert::equal("expectedFile", $result[0]["variables"][1]->getName());
        Assert::equal(VariableTypes::$FILE_TYPE, $result[0]["variables"][0]->getType());
        Assert::equal(VariableTypes::$FILE_TYPE, $result[0]["variables"][1]->getType());
    }

    public function testVariablesForExerciseNonEmptyJoin()
    {
        $pipelineAid = "pipelineA";
        $pipelineA = $this->loader->loadPipeline(
            [
                "variables" => [],
                "boxes" => [
                    [
                        "name" => "input",
                        "type" => "file-in",
                        "portsIn" => [],
                        "portsOut" => ["input" => ['type' => 'file', 'value' => "input"]]
                    ],
                    [
                        "name" => "file",
                        "type" => "file-out",
                        "portsIn" => ["output" => ['type' => 'file', 'value' => "join"]],
                        "portsOut" => []
                    ]
                ]
            ]
        );
        $pipelineBid = "pipelineB";
        $pipelineB = $this->loader->loadPipeline(
            [
                "variables" => [],
                "boxes" => [
                    [
                        "name" => "test",
                        "type" => "file-in",
                        "portsIn" => [],
                        "portsOut" => ["input" => ['type' => 'file', 'value' => "test"]]
                    ],
                    [
                        "name" => "file",
                        "type" => "file-in",
                        "portsIn" => [],
                        "portsOut" => ["input" => ['type' => 'file', 'value' => "join"]]
                    ]
                ]
            ]
        );

        $this->pipelinesCache->shouldReceive("getPipelineConfig")->with($pipelineAid)->andReturn($pipelineA);
        $this->pipelinesCache->shouldReceive("getPipelineConfig")->with($pipelineBid)->andReturn($pipelineB);

        $result = $this->helper->getVariablesForExercise([$pipelineAid, $pipelineBid], new VariablesTable());
        Assert::count(2, $result);
        Assert::equal($pipelineAid, $result[0]["id"]);
        Assert::equal($pipelineBid, $result[1]["id"]);

        Assert::count(1, $result[0]["variables"]);
        Assert::count(1, $result[1]["variables"]);

        Assert::equal("input", $result[0]["variables"][0]->getName());
        Assert::equal("test", $result[1]["variables"][0]->getName());

        Assert::equal(VariableTypes::$REMOTE_FILE_TYPE, $result[0]["variables"][0]->getType());
        Assert::equal(VariableTypes::$REMOTE_FILE_TYPE, $result[1]["variables"][0]->getType());
    }

    public function testVariablesForExerciseVariableFromVariablesTable()
    {
        $pipelineAid = "pipelineA";
        $pipelineA = $this->loader->loadPipeline(
            [
                "variables" => [],
                "boxes" => [
                    [
                        "name" => "input",
                        "type" => "file-in",
                        "portsIn" => [],
                        "portsOut" => ["input" => ['type' => 'file', 'value' => "input"]]
                    ],
                    [
                        "name" => "file",
                        "type" => "file-out",
                        "portsIn" => ["output" => ['type' => 'file', 'value' => "join"]],
                        "portsOut" => []
                    ]
                ]
            ]
        );
        $pipelineBid = "pipelineB";
        $pipelineB = $this->loader->loadPipeline(
            [
                "variables" => [],
                "boxes" => [
                    [
                        "name" => "test",
                        "type" => "file-in",
                        "portsIn" => [],
                        "portsOut" => ["input" => ['type' => 'file', 'value' => "test"]]
                    ],
                    [
                        "name" => "file",
                        "type" => "file-in",
                        "portsIn" => [],
                        "portsOut" => ["input" => ['type' => 'file', 'value' => "join"]]
                    ]
                ]
            ]
        );
        $variablesTable = $this->loader->loadVariablesTable(
            [
                ["name" => "test", "type" => "file", "value" => "test.in"]
            ]
        );

        $this->pipelinesCache->shouldReceive("getPipelineConfig")->with($pipelineAid)->andReturn($pipelineA);
        $this->pipelinesCache->shouldReceive("getPipelineConfig")->with($pipelineBid)->andReturn($pipelineB);

        $result = $this->helper->getVariablesForExercise([$pipelineAid, $pipelineBid], $variablesTable);
        Assert::count(2, $result);
        Assert::equal($pipelineAid, $result[0]["id"]);
        Assert::equal($pipelineBid, $result[1]["id"]);

        Assert::count(1, $result[0]["variables"]);
        Assert::count(0, $result[1]["variables"]);

        Assert::equal("input", $result[0]["variables"][0]->getName());
        Assert::equal(VariableTypes::$REMOTE_FILE_TYPE, $result[0]["variables"][0]->getType());
    }

    public function testVariablesForExerciseComplexJoin()
    {
        $pipelineAid = "pipelineA";
        $pipelineA = $this->loader->loadPipeline(
            [
                "variables" => [],
                "boxes" => [
                    [
                        "name" => "input",
                        "type" => "file-in",
                        "portsIn" => [],
                        "portsOut" => ["input" => ['type' => 'file', 'value' => "input"]]
                    ],
                    [
                        "name" => "file",
                        "type" => "file-out",
                        "portsIn" => ["output" => ['type' => 'file', 'value' => "join"]],
                        "portsOut" => []
                    ]
                ]
            ]
        );
        $pipelineBid = "pipelineB";
        $pipelineB = $this->loader->loadPipeline(
            [
                "variables" => [],
                "boxes" => [
                    [
                        "name" => "test",
                        "type" => "file-in",
                        "portsIn" => [],
                        "portsOut" => ["input" => ['type' => 'file', 'value' => "test"]]
                    ],
                    [
                        "name" => "file",
                        "type" => "file-in",
                        "portsIn" => [],
                        "portsOut" => ["input" => ['type' => 'file', 'value' => "join"]]
                    ],
                    [
                        "name" => "output",
                        "type" => "file-out",
                        "portsIn" => ["output" => ['type' => 'file', 'value' => "join-second"]],
                        "portsOut" => []
                    ]
                ]
            ]
        );
        $pipelineCid = "pipelineC";
        $pipelineC = $this->loader->loadPipeline(
            [
                "variables" => [],
                "boxes" => [
                    [
                        "name" => "environment",
                        "type" => "file-in",
                        "portsIn" => [],
                        "portsOut" => ["input" => ['type' => 'file', 'value' => "environment"]]
                    ],
                    [
                        "name" => "non-environment-a",
                        "type" => "file-in",
                        "portsIn" => [],
                        "portsOut" => ["input" => ['type' => 'file', 'value' => "non-environment-a"]]
                    ],
                    [
                        "name" => "non-environment-b",
                        "type" => "file-in",
                        "portsIn" => [],
                        "portsOut" => ["input" => ['type' => 'file', 'value' => "non-environment-b"]]
                    ],
                    [
                        "name" => "input",
                        "type" => "file-in",
                        "portsIn" => [],
                        "portsOut" => ["input" => ['type' => 'file', 'value' => "join-second"]]
                    ]
                ]
            ]
        );
        $variablesTable = $this->loader->loadVariablesTable(
            [
                ["name" => "test", "type" => "file", "value" => "test.in"],
                ["name" => "environment", "type" => "file", "value" => "environment"]
            ]
        );

        $this->pipelinesCache->shouldReceive("getPipelineConfig")->with($pipelineAid)->andReturn($pipelineA);
        $this->pipelinesCache->shouldReceive("getPipelineConfig")->with($pipelineBid)->andReturn($pipelineB);
        $this->pipelinesCache->shouldReceive("getPipelineConfig")->with($pipelineCid)->andReturn($pipelineC);

        $result = $this->helper->getVariablesForExercise([$pipelineAid, $pipelineBid, $pipelineCid], $variablesTable);
        Assert::count(3, $result);
        Assert::equal($pipelineAid, $result[0]["id"]);
        Assert::equal($pipelineBid, $result[1]["id"]);
        Assert::equal($pipelineCid, $result[2]["id"]);

        Assert::count(1, $result[0]["variables"]);
        Assert::count(0, $result[1]["variables"]);
        Assert::count(2, $result[2]["variables"]);

        Assert::equal("input", $result[0]["variables"][0]->getName());
        Assert::equal("non-environment-a", $result[2]["variables"][0]->getName());
        Assert::equal("non-environment-b", $result[2]["variables"][1]->getName());

        Assert::equal(VariableTypes::$REMOTE_FILE_TYPE, $result[0]["variables"][0]->getType());
        Assert::equal(VariableTypes::$REMOTE_FILE_TYPE, $result[2]["variables"][0]->getType());
        Assert::equal(VariableTypes::$REMOTE_FILE_TYPE, $result[2]["variables"][1]->getType());
    }

    public function testEnvironmentsForFilesNotMatched()
    {
        $exercise = $this->createExercise();
        $result = $this->helper->getEnvironmentsForFiles($exercise, ["main.cpp"]);

        Assert::equal([], $result);
    }

    public function testEnvironmentsForFilesSingleMatched()
    {
        $exercise = $this->createExercise();
        $result = $this->helper->getEnvironmentsForFiles($exercise, ["main.c"]);

        Assert::equal(["c-gcc-linux"], $result);
    }

    public function testEnvironmentsForFilesNotAllMatched()
    {
        $exercise = $this->createExercise();
        $result = $this->helper->getEnvironmentsForFiles($exercise, ["main.c", "main.java"]);

        Assert::equal([], $result);
    }

    public function testSubmitVariables()
    {
        $exercise = $this->createExercise();
        $result = $this->helper->getSubmitVariablesForExercise($exercise);
        Assert::count(2, $result);

        $cRuntime = $result[0];
        Assert::equal("c-gcc-linux", $cRuntime["runtimeEnvironmentId"]);
        Assert::count(2, $cRuntime["variables"]);
        Assert::equal("c-submit-file", $cRuntime["variables"][0]["name"]);
        Assert::equal("file", $cRuntime["variables"][0]["type"]);
        Assert::equal("c-submit-exercise-file", $cRuntime["variables"][1]["name"]);
        Assert::equal("file", $cRuntime["variables"][1]["type"]);

        $javaRuntime = $result[1];
        Assert::equal("java-linux", $javaRuntime["runtimeEnvironmentId"]);
        Assert::count(2, $javaRuntime["variables"]);
        Assert::equal("java-submit-file", $javaRuntime["variables"][0]["name"]);
        Assert::equal("file", $javaRuntime["variables"][0]["type"]);
        Assert::equal("java-submit-exercise-file", $javaRuntime["variables"][1]["name"]);
        Assert::equal("file", $javaRuntime["variables"][1]["type"]);
    }


    private function createExercise(): IExercise
    {
        $user = new User("", "", "", "", "", "", new Instance());

        $cRuntime = new RuntimeEnvironment("c-gcc-linux", "C (GCC)", "c", "*.c", "Linux", "");
        $javaRuntime = new RuntimeEnvironment("java-linux", "Java", "java", "*.java", "Linux", "");

        $cEnvConfig = new ExerciseEnvironmentConfig($cRuntime, Yaml::dump(self::$cEnvVariablesTable), $user);
        $javaEnvConfig = new ExerciseEnvironmentConfig($javaRuntime, Yaml::dump(self::$javaEnvVariablesTable), $user);

        $exerciseConfig = new ExerciseConfig(Yaml::dump(self::$exerciseConfig), $user);

        $exercise = Exercise::create($user, new Group("ext", new Instance()));
        $exercise->addRuntimeEnvironment($cRuntime);
        $exercise->addRuntimeEnvironment($javaRuntime);
        $exercise->addExerciseEnvironmentConfig($cEnvConfig);
        $exercise->addExerciseEnvironmentConfig($javaEnvConfig);
        $exercise->setExerciseConfig($exerciseConfig);
        return $exercise;
    }
}

# Testing methods run
(new TestExerciseConfigHelper())->run();
