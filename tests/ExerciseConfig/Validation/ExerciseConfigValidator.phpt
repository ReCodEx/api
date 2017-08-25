<?php

include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Exceptions\NotFoundException;
use App\Helpers\ExerciseConfig\Environment;
use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Helpers\ExerciseConfig\PipelineVars;
use App\Helpers\ExerciseConfig\Test;
use App\Helpers\ExerciseConfig\Validation\ExerciseConfigValidator;
use App\Model\Repository\Pipelines;
use Tester\Assert;


class TestExerciseConfigValidator extends Tester\TestCase
{
  /**
   * @var Mockery\Mock | Pipelines
   */
  private $mockPipelines;

  /**
   * @var Mockery\Mock | \App\Model\Entity\Pipeline
   */
  private $mockPipelineEntity;

  /**
   * @var Mockery\Mock | \App\Model\Entity\PipelineConfig
   */
  private $mockPipelineConfigEntity;

  /**
   * @var ExerciseConfigValidator
   */
  private $validator;

  public function __construct() {
    $this->mockPipelines = Mockery::mock(Pipelines::class);
    $this->validator = new ExerciseConfigValidator($this->mockPipelines, new Loader(new BoxService()));

    $this->mockPipelineConfigEntity = Mockery::mock(\App\Model\Entity\PipelineConfig::class);
    $this->mockPipelineConfigEntity->shouldReceive("getParsedPipeline")->andReturn([
      "boxes" => [],
      "variables" => []
    ]);

    $this->mockPipelineEntity = Mockery::mock(\App\Model\Entity\Pipeline::class);
    $this->mockPipelineEntity->shouldReceive("getPipelineConfig")->andReturn($this->mockPipelineConfigEntity);
  }


  public function testMissingEnvironment() {
    $exerciseConfig = new ExerciseConfig();
    $variablesTables = [
      "envA" => null,
      "envB" => null
    ];

    Assert::exception(function () use ($exerciseConfig, $variablesTables) {
      $this->validator->validate($exerciseConfig, $variablesTables);
    }, ExerciseConfigException::class);
  }

  public function testDifferentEnvironments() {
    $exerciseConfig = new ExerciseConfig();
    $exerciseConfig->addEnvironment("envA");
    $exerciseConfig->addEnvironment("envB");

    $variablesTables = [
      "envC" => null,
      "envD" => null
    ];

    Assert::exception(function () use ($exerciseConfig, $variablesTables) {
      $this->validator->validate($exerciseConfig, $variablesTables);
    }, ExerciseConfigException::class);
  }

  public function testDifferentNumberOfEnvironments() {
    $exerciseConfig = new ExerciseConfig();
    $exerciseConfig->addEnvironment("envA");

    $variablesTables = [
      "envA" => null,
      "envB" => null
    ];

    Assert::exception(function () use ($exerciseConfig, $variablesTables) {
      $this->validator->validate($exerciseConfig, $variablesTables);
    }, ExerciseConfigException::class);
  }

  public function testMissingDefaultPipeline() {
    $pipelineVars = new PipelineVars();
    $pipelineVars->setName("not existing pipeline");

    $test = new Test();
    $test->addPipeline($pipelineVars);

    $exerciseConfig = new ExerciseConfig();
    $exerciseConfig->addEnvironment("envA");
    $exerciseConfig->addTest("testA", $test);

    $variablesTables = [
      "envA" => null
    ];

    // setup mock pipelines
    $this->mockPipelines->shouldReceive("findOrThrow")->withArgs(["not existing pipeline"])->andThrow(NotFoundException::class);

    // missing in defaults
    Assert::exception(function () use ($exerciseConfig, $variablesTables) {
      $this->validator->validate($exerciseConfig, $variablesTables);
    }, ExerciseConfigException::class);
  }

  public function testMissingEnvironmentPipeline() {
    $existing = new PipelineVars();
    $notExisting = new PipelineVars();
    $existing->setName("existing pipeline");
    $notExisting->setName("not existing pipeline");

    $environment = new Environment();
    $environment->addPipeline($notExisting);

    $test = new Test();
    $test->addPipeline($existing);
    $test->addEnvironment("envA", $environment);

    $exerciseConfig = new ExerciseConfig();
    $exerciseConfig->addEnvironment("envA");
    $exerciseConfig->addTest("testA", $test);

    $variablesTables = [
      "envA" => null
    ];

    // setup mock pipelines
    $this->mockPipelines->shouldReceive("findOrThrow")->withArgs(["not existing pipeline"])->andThrow(NotFoundException::class);
    $this->mockPipelines->shouldReceive("findOrThrow")->withArgs(["existing pipeline"])->andReturn($this->mockPipelineEntity);

    // missing in environments
    Assert::exception(function () use ($exerciseConfig, $variablesTables) {
      $this->validator->validate($exerciseConfig, $variablesTables);
    }, ExerciseConfigException::class);
  }

  public function testEmpty() {
    $exerciseConfig = new ExerciseConfig();
    $variablesTables = [];

    Assert::noError(
      function () use ($exerciseConfig, $variablesTables) {
        $this->validator->validate($exerciseConfig, $variablesTables);
      }
    );
  }

  public function testCorrect() {
    $existing = new PipelineVars();
    $existing->setName("existing pipeline");

    $environment = new Environment();
    $environment->addPipeline($existing);

    $test = new Test();
    $test->addPipeline($existing);
    $test->addEnvironment("envA", $environment);

    $exerciseConfig = new ExerciseConfig();
    $exerciseConfig->addEnvironment("envA");
    $exerciseConfig->addTest("testA", $test);

    $variablesTables = [
      "envA" => null
    ];

    // setup mock pipelines
    $this->mockPipelines->shouldReceive("findOrThrow")->withArgs(["existing pipeline"])->andReturn($this->mockPipelineEntity);

    Assert::noError(
      function () use ($exerciseConfig, $variablesTables) {
        $this->validator->validate($exerciseConfig, $variablesTables);
      }
    );
  }

}

# Testing methods run
$testCase = new TestExerciseConfigValidator;
$testCase->run();
