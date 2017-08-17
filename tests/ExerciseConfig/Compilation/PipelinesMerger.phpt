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
  private $mockHelloPipelineConfig;

  /** @var Mockery\Mock | \App\Model\Entity\PipelineConfig */
  private $mockWorldPipelineConfig;

  /** @var PipelinesMerger */
  private $merger;


  private static $config;
  private static $envVariablesTable;
  private static $environment;
  private static $pipelineHello;
  private static $pipelineWorld;


  public function __construct() {

    // mock pipelines repository
    $mockPipelines = Mockery::mock(Pipelines::class);
    // construct all services
    $this->boxService = new BoxService();
    $this->loader = new Loader($this->boxService);
    $this->merger = new PipelinesMerger($mockPipelines, $this->loader);

    // mock entities and stuff
    $this->mockHelloPipelineConfig = Mockery::mock(\App\Model\Entity\PipelineConfig::class);
    $mockHelloPipeline = Mockery::mock(\App\Model\Entity\Pipeline::class);
    $mockHelloPipeline->shouldReceive("getPipelineConfig")->andReturn($this->mockHelloPipelineConfig);
    $this->mockWorldPipelineConfig = Mockery::mock(\App\Model\Entity\PipelineConfig::class);
    $mockWorldPipeline = Mockery::mock(\App\Model\Entity\Pipeline::class);
    $mockWorldPipeline->shouldReceive("getPipelineConfig")->andReturn($this->mockWorldPipelineConfig);

    // set up mocked pipelines repository
    $mockPipelines->shouldReceive("findOrThrow")->with("hello")->andReturn($mockHelloPipeline);
    $mockPipelines->shouldReceive("findOrThrow")->with("world")->andReturn($mockWorldPipeline);
  }


  protected function setUp() {
    self::$config = [
      "environments" => [ "envA", "envB" ],
      "tests" => [
        "testA" => [
          "pipelines" => [
            "hello" => [
              "variables" => [ [ "name" => "world", "type" => "string", "value" => "hello" ] ]
            ]
          ],
          "environments" => [
            "envA" => [ "pipelines" => [] ],
            "envB" => [ "pipelines" => [] ]
          ]
        ],
        "testB" => [
          "pipelines" => [
            "world" => [
              "variables" => [ [ "name" => "hello", "type" => "string", "value" => "world" ] ]
            ]
          ],
          "environments" => [
            "envA" => [
              "pipelines" => [
                "world" => [
                  "variables" => [ [ "name" => "hello", "type" => "string", "value" => "world envA" ] ]
                ]
              ]
            ],
            "envB" => [
              "pipelines" => [
                "world" => [
                  "variables" => [ [ "name" => "hello", "type" => "string", "value" => "world envB" ] ]
                ]
              ]
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

    self::$pipelineHello = [
      "boxes" => [],
      "variables" => []
    ];
    self::$pipelineWorld = [
      "boxes" => [],
      "variables" => []
    ];
  }


  public function testEmptyTests() {
    $this->mockHelloPipelineConfig->shouldReceive("getParsedPipeline")->andReturn(self::$pipelineHello);
    $this->mockWorldPipelineConfig->shouldReceive("getParsedPipeline")->andReturn(self::$pipelineWorld);

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
    $this->mockHelloPipelineConfig->shouldReceive("getParsedPipeline")->andReturn(self::$pipelineHello);
    $this->mockWorldPipelineConfig->shouldReceive("getParsedPipeline")->andReturn(self::$pipelineWorld);
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
