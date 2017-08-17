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

  /** @var Mockery\Mock | Pipelines */
  private $mockPipelines;

  /** @var Mockery\Mock | \App\Model\Entity\Pipeline */
  private $mockPipelineEntity;

  /** @var Mockery\Mock | \App\Model\Entity\PipelineConfig */
  private $mockPipelineConfigEntity;

  /** @var PipelinesMerger */
  private $merger;


  private static $config;
  private static $envVariablesTable;
  private static $environment;


  public function __construct() {

    // mock pipelines repository
    $this->mockPipelines = Mockery::mock(Pipelines::class);
    // construct all services
    $this->boxService = new BoxService();
    $this->loader = new Loader($this->boxService);
    $this->merger = new PipelinesMerger($this->mockPipelines, $this->loader);

    // mock entities and stuff
    $this->mockPipelineConfigEntity = Mockery::mock(\App\Model\Entity\PipelineConfig::class);
    $this->mockPipelineConfigEntity->shouldReceive("getParsedPipeline")->andReturn([
      "boxes" => [],
      "variables" => []
    ]);
    $this->mockPipelineEntity = Mockery::mock(\App\Model\Entity\Pipeline::class);
    $this->mockPipelineEntity->shouldReceive("getPipelineConfig")->andReturn($this->mockPipelineConfigEntity);

    // set up mocked pipelines repository
    $this->mockPipelines->shouldReceive("findOrThrow")->with("hello")->andReturn($this->mockPipelineEntity);
    $this->mockPipelines->shouldReceive("findOrThrow")->with("world")->andReturn($this->mockPipelineEntity);
  }


  protected function setUp() {
    self::$config = [
      "environments" => [ "envA", "envB" ],
      "tests" => [
        "testA" => [
          "pipelines" => [ [
              "name" => "hello",
              "variables" => [
                [ "name" => "world", "type" => "string", "value" => "hello" ]
              ]
            ]
          ],
          "environments" => [
            "envA" => [
              "pipelines" => []
            ],
            "envB" => [
              "pipelines" => []
            ]
          ]
        ],
        "testB" => [
          "pipelines" => [ [
              "name" => "world",
              "variables" => [
                [ "name" => "hello", "type" => "string", "value" => "world" ]
              ]
            ]
          ],
          "environments" => [
            "envA" => [
              "pipelines" => []
            ],
            "envB" => [
              "pipelines" => []
            ]
          ]
        ]
      ]
    ];
    self::$envVariablesTable = [
      [ "name" => "environment", "type" => "file", "value" => "envVar" ],
      [ "name" => "tnemnorivne", "type" => "string", "value" => "vneVar" ],
      [ "name" => "varFileArr", "type" => "file[]", "value" => "envFileArrVar" ],
      [ "name" => "varStringArr", "type" => "string[]", "value" => "envStringArrVar" ]
    ];
    self::$environment = "envA";
  }


  public function testEmpty() {
    $tests = $this->merger->merge(new ExerciseConfig(), new VariablesTable(), "");
    Assert::true(is_array($tests));
    Assert::count(0, $tests);
  }

  public function testCorrect() {
    $config = $this->loader->loadExerciseConfig(self::$config);
    $envVariablesTable = $this->loader->loadVariablesTable(self::$envVariablesTable);
    $tests = $this->merger->merge($config, $envVariablesTable, self::$environment);
    Assert::count(2, $tests);

    $testA = $tests["testA"];
    $testB = $tests["testB"];
    Assert::type(MergeTree::class, $testA);
    Assert::type(MergeTree::class, $testB);
  }

}

# Testing methods run
$testCase = new TestPipelinesMerger();
$testCase->run();
