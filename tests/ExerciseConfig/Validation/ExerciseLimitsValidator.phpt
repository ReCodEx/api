<?php

$container = include '../../bootstrap.php';

use App\Helpers\ExerciseConfig\Environment;
use App\Helpers\ExerciseConfig\ExerciseConfig;
use App\Helpers\ExerciseConfig\ExerciseLimits;
use App\Helpers\ExerciseConfig\Limits;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Test;
use App\Helpers\ExerciseConfig\Validation\ExerciseLimitsValidator;
use App\Model\Entity\Pipeline;
use App\Model\Repository\Pipelines;
use Kdyby\Doctrine\EntityManager;
use Nette\DI\Container;
use Tester\Assert;


/**
 * @testCase
 */
class TestExerciseLimitsValidator extends Tester\TestCase
{
  /** @var Container */
  private $container;

  /** @var ExerciseLimitsValidator */
  private $validator;

  /** @var Pipelines */
  private $pipelines;

  /** @var EntityManager  */
  private $em;

  /** @var Loader */
  private $loader;

  public function __construct(Container $container) {
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
  }

  private static $config = [
    "environments" => [ "envA", "envB" ],
    "tests" => [
      "testA" => [
        "pipelines" => [ [
          "name" => "compilationPipeline",
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
          "name" => "compilationPipeline",
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

  protected function setUp() {
    PresenterTestHelper::fillDatabase($this->container);
    $this->validator = $this->container->getByType(ExerciseLimitsValidator::class);
    $this->pipelines = $this->container->getByType(Pipelines::class);
    $this->loader = $this->container->getByType(Loader::class);
  }

  public function testCorrect() {
    $limits = new ExerciseLimits();
    $limits->addLimits("testA", "compilationPipeline", "compilation", Limits::create(2.0, 10, 1));
    $limits->addLimits("testA", "compilationPipeline", "output", Limits::create(2.0, 10, 1));

    $environmentId = "envA";

    $config = $this->loader->loadExerciseConfig(static::$config);

    Assert::noError(function () use ($limits, $config, $environmentId) {
      $this->validator->validate($limits, $config, $environmentId);
    });
  }

}

$testCase = new TestExerciseLimitsValidator($container);
$testCase->run();
