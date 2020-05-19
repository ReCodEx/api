<?php

$container = include '../../bootstrap.php';

use App\Exceptions\ExerciseConfigException;
use App\Helpers\EntityMetadata\HwGroupMeta;
use App\Helpers\ExerciseConfig\ExerciseLimits;
use App\Helpers\ExerciseConfig\Limits;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Validation\ExerciseLimitsValidator;
use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseTest;
use App\Model\Entity\Group;
use App\Model\Entity\Instance;
use App\Model\Entity\User;
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

    /** @var EntityManager */
    private $em;

    /** @var Loader */
    private $loader;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
        $this->validator = $this->container->getByType(ExerciseLimitsValidator::class);
        $this->pipelines = $this->container->getByType(Pipelines::class);
        $this->loader = $this->container->getByType(Loader::class);
    }


    public function testDifferentTests()
    {
        $limits = new ExerciseLimits();
        $limits->addLimits("1", Limits::create(2.0, 3.0, 10, 1));
        $limits->addLimits("2", Limits::create(2.0, 3.0, 10, 1));
        $limits->addLimits("3", Limits::create(2.0, 3.0, 10, 1));

        $exercise = $this->createExercise();
        $this->addTwoTestsToExercise($exercise);

        Assert::exception(
            function () use ($exercise, $limits) {
                $this->validator->validate($exercise, $this->createHwMeta(), $limits);
            },
            ExerciseConfigException::class,
            "Exercise configuration error - Test with id '3' is not present in the exercise configuration"
        );
    }

    public function testMissingTests()
    {
        $limits = new ExerciseLimits();
        $limits->addLimits("1", Limits::create(2.0, 3.0, 10, 1));

        $exercise = $this->createExercise();
        $this->addTwoTestsToExercise($exercise);

        Assert::exception(
            function () use ($exercise, $limits) {
                $this->validator->validate($exercise, $this->createHwMeta(), $limits);
            },
            ExerciseConfigException::class,
            "Exercise configuration error - Test 'Test B' does not have any limits specified"
        );
    }

    public function testMemoryHighAF()
    {
        $limits = new ExerciseLimits();
        $limits->addLimits("1", Limits::create(2.0, 3.0, 1234567890, 1));
        $limits->addLimits("2", Limits::create(2.0, 3.0, 10, 1));

        $exercise = $this->createExercise();
        $this->addTwoTestsToExercise($exercise);

        Assert::exception(
            function () use ($exercise, $limits) {
                $this->validator->validate($exercise, $this->createHwMeta(), $limits);
            },
            ExerciseConfigException::class,
            "Exercise configuration error - Test 'Test A' has exceeded memory limit '1048576'"
        );
    }

    public function testCorrect()
    {
        $limits = new ExerciseLimits();
        $limits->addLimits("1", Limits::create(2.0, 3.0, 10, 1));
        $limits->addLimits("2", Limits::create(2.0, 3.0, 10, 1));

        $exercise = $this->createExercise();
        $this->addTwoTestsToExercise($exercise);

        Assert::noError(
            function () use ($exercise, $limits) {
                $this->validator->validate($exercise, $this->createHwMeta(), $limits);
            }
        );
    }


    private function createHwMeta(): HwGroupMeta
    {
        $hwGroupMeta = new HwGroupMeta(
            "memory: 1048576\ncpuTimePerTest: 60\ncpuTimePerExercise: 300\nwallTimePerTest: 60\nwallTimePerExercise: 300"
        );
        return $hwGroupMeta;
    }

    /**
     * @return Exercise
     */
    private function createExercise(): Exercise
    {
        $user = $this->getDummyUser();
        $exercise = Exercise::create($user, new Group("ext", new Instance()));
        return $exercise;
    }

    /**
     * @param Exercise $exercise
     * @return Exercise
     */
    private function addTwoTestsToExercise(Exercise $exercise): Exercise
    {
        $user = $exercise->getAuthor();
        $testA = new ExerciseTest("Test A", "descA", $user);
        $testB = new ExerciseTest("Test B", "descB", $user);
        $testA->setId(1);
        $testB->setId(2);
        $exercise->addExerciseTest($testA);
        $exercise->addExerciseTest($testB);
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

$testCase = new TestExerciseLimitsValidator($container);
$testCase->run();
