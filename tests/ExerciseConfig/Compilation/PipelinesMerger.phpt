<?php

include '../../bootstrap.php';

use App\Helpers\ExerciseConfig\Compilation\PipelinesMerger;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
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
  }

  public function testTrue() {
    Assert::true(false);
  }

}

# Testing methods run
$testCase = new TestPipelinesMerger();
$testCase->run();
