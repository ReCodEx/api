<?php

$container = include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\ExerciseConfig\Environment;
use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\Helper;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use App\Helpers\ExerciseConfig\PipelineVars;
use App\Helpers\ExerciseConfig\Test;
use App\Helpers\ExerciseConfig\Validation\ExerciseConfigValidator;
use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseEnvironmentConfig;
use App\Model\Entity\ExerciseTest;
use App\Model\Entity\Instance;
use App\Model\Entity\RuntimeEnvironment;
use App\Model\Entity\User;
use App\Model\Repository\Pipelines;
use Nette\DI\Container;
use Tester\Assert;


/**
 * @testCase
 */
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

  private $container;

  public function __construct(Container $container) {
    $this->mockPipelines = Mockery::mock(Pipelines::class);
    $this->validator = new ExerciseConfigValidator($this->mockPipelines, new Loader(new BoxService()), new Helper());
    $this->container = $container;

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
    $exercise = $this->createExercise();
    $this->addTwoEnvironmentsToExercise($exercise);
    $this->addTwoTestsToExercise($exercise);

    Assert::exception(function () use ($exerciseConfig, $exercise) {
      $this->validator->validate($exerciseConfig, $exercise);
    }, ExerciseConfigException::class);
  }

  public function testDifferentEnvironments() {
    $exerciseConfig = new ExerciseConfig();
    $exerciseConfig->addEnvironment("envA");
    $exerciseConfig->addEnvironment("envB");

    $exercise = $this->createExercise();
    $user = $exercise->getAuthor();
    $this->addTwoTestsToExercise($exercise);

    $envC = new RuntimeEnvironment("envC", "Env C", "C", ".c", "", "");
    $envD = new RuntimeEnvironment("envD", "Env D", "D", ".d", "", "");
    $exercise->addExerciseEnvironmentConfig(new ExerciseEnvironmentConfig($envC, "",  $user, NULL));
    $exercise->addExerciseEnvironmentConfig(new ExerciseEnvironmentConfig($envD, "",  $user, NULL));

    Assert::exception(function () use ($exerciseConfig, $exercise) {
      $this->validator->validate($exerciseConfig, $exercise);
    }, ExerciseConfigException::class);
  }

  public function testDifferentNumberOfEnvironments() {
    $exerciseConfig = new ExerciseConfig();
    $exercise = $this->createExercise();
    $this->addTwoEnvironmentsToExercise($exercise);
    $exerciseConfig->addEnvironment("envA");

    Assert::exception(function () use ($exerciseConfig, $exercise) {
      $this->validator->validate($exerciseConfig, $exercise);
    }, ExerciseConfigException::class);
  }

  public function testMissingEnvironmentPipeline() {
    $notExisting = new PipelineVars();
    $notExisting->setName("not existing pipeline");

    $environment = new Environment();
    $environment->addPipeline($notExisting);

    $test = new Test();
    $test->addEnvironment("envA", $environment);

    $exerciseConfig = new ExerciseConfig();
    $exerciseConfig->addEnvironment("envA");
    $exerciseConfig->addTest("Test A", $test);
    $exerciseConfig->addTest("Test B", $test);

    $exercise = $this->createExercise();
    $this->addTwoTestsToExercise($exercise);
    $this->addSingleEnvironmentToExercise($exercise);

    // setup mock pipelines
    $this->mockPipelines->shouldReceive("get")->withArgs(["not existing pipeline"])->andReturn(NULL);
    $this->mockPipelines->shouldReceive("get")->withArgs(["existing pipeline"])->andReturn($this->mockPipelineEntity);

    // missing in environments
    Assert::exception(function () use ($exerciseConfig, $exercise) {
      $this->validator->validate($exerciseConfig, $exercise);
    }, ExerciseConfigException::class);
  }

  public function testDifferentNumberOfTests() {
    $existing = new PipelineVars();
    $existing->setName("existing pipeline");

    $environment = new Environment();
    $environment->addPipeline($existing);

    $test = new Test();
    $test->addEnvironment("envA", $environment);

    $exerciseConfig = new ExerciseConfig();
    $exerciseConfig->addEnvironment("envA");
    $exerciseConfig->addTest("Test A", $test);

    $exercise = $this->createExercise();
    $this->addTwoTestsToExercise($exercise);
    $this->addSingleEnvironmentToExercise($exercise);

    // setup mock pipelines
    $this->mockPipelines->shouldReceive("get")->withArgs(["existing pipeline"])->andReturn($this->mockPipelineEntity);

    Assert::exception(function () use ($exerciseConfig, $exercise) {
      $this->validator->validate($exerciseConfig, $exercise);
    }, ExerciseConfigException::class, "Exercise configuration error - Number of tests in configuration do not correspond to the ones in exercise");
  }

  public function testDifferentTestNames() {
    $existing = new PipelineVars();
    $existing->setName("existing pipeline");

    $environment = new Environment();
    $environment->addPipeline($existing);

    $test = new Test();
    $test->addEnvironment("envA", $environment);

    $exerciseConfig = new ExerciseConfig();
    $exerciseConfig->addEnvironment("envA");
    $exerciseConfig->addTest("Test 1", $test);
    $exerciseConfig->addTest("Test 2", $test);

    $exercise = $this->createExercise();
    $this->addTwoTestsToExercise($exercise);
    $this->addSingleEnvironmentToExercise($exercise);

    // setup mock pipelines
    $this->mockPipelines->shouldReceive("get")->withArgs(["existing pipeline"])->andReturn($this->mockPipelineEntity);

    Assert::exception(function () use ($exerciseConfig, $exercise) {
      $this->validator->validate($exerciseConfig, $exercise);
    }, ExerciseConfigException::class, "Exercise configuration error - Test 'Test 1' not found in exercise tests");
  }

  public function testEmpty() {
    $exerciseConfig = new ExerciseConfig();
    $user = $this->getDummyUser();
    $exercise = Exercise::create($user);

    Assert::noError(
      function () use ($exerciseConfig, $exercise) {
        $this->validator->validate($exerciseConfig, $exercise);
      }
    );
  }

  public function testCorrect() {
    $existing = new PipelineVars();
    $existing->setName("existing pipeline");

    $environment = new Environment();
    $environment->addPipeline($existing);

    $test = new Test();
    $test->addEnvironment("envA", $environment);

    $exerciseConfig = new ExerciseConfig();
    $exerciseConfig->addEnvironment("envA");
    $exerciseConfig->addTest("Test A", $test);
    $exerciseConfig->addTest("Test B", $test);

    $exercise = $this->createExercise();
    $this->addTwoTestsToExercise($exercise);
    $this->addSingleEnvironmentToExercise($exercise);

    // setup mock pipelines
    $this->mockPipelines->shouldReceive("get")->withArgs(["existing pipeline"])->andReturn($this->mockPipelineEntity);

    Assert::noError(
      function () use ($exerciseConfig, $exercise) {
        $this->validator->validate($exerciseConfig, $exercise);
      }
    );
  }

  /**
   * @return Exercise
   */
  private function createExercise(): Exercise {
    $user = $this->getDummyUser();
    $exercise = Exercise::create($user);
    return $exercise;
  }

  /**
   * @param Exercise $exercise
   * @return Exercise
   */
  private function addTwoEnvironmentsToExercise(Exercise $exercise): Exercise
  {
    $user = $exercise->getAuthor();
    $envA = new RuntimeEnvironment("envA", "Env A", "A", ".a", "", "");
    $envB = new RuntimeEnvironment("envB", "Env B", "B", ".b", "", "");
    $exercise->addExerciseEnvironmentConfig(new ExerciseEnvironmentConfig($envA, "[]", $user, NULL));
    $exercise->addExerciseEnvironmentConfig(new ExerciseEnvironmentConfig($envB, "[]", $user, NULL));
    return $exercise;
  }

  /**
   * @param Exercise $exercise
   * @return Exercise
   */
  private function addTwoTestsToExercise(Exercise $exercise): Exercise {
    $user = $exercise->getAuthor();
    $testA = new ExerciseTest("Test A", "descA", $user);
    $testB = new ExerciseTest("Test B", "descB", $user);
    $exercise->addExerciseTest($testA);
    $exercise->addExerciseTest($testB);
    return $exercise;
  }

  /**
   * @param Exercise $exercise
   * @return Exercise
   */
  private function addSingleEnvironmentToExercise(Exercise $exercise): Exercise
  {
    $user = $exercise->getAuthor();
    $envA = new RuntimeEnvironment("envA", "Env A", "A", ".a", "", "");
    $exercise->addExerciseEnvironmentConfig(new ExerciseEnvironmentConfig($envA, "[]", $user, NULL));
    return $exercise;
  }

  /**
   * @return User
   */
  private function getDummyUser(): User
  {
    $user = new User("", "", "", "", "", "", new Instance());
    return $user;
  }
}

# Testing methods run
$testCase = new TestExerciseConfigValidator($container);
$testCase->run();
