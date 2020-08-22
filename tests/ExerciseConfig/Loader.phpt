<?php

include '../bootstrap.php';

use App\Helpers\ExerciseConfig\Pipeline\Box\BoxService;
use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;

/**
 * Exercise configuration builder is only tested in components which are
 * constructed/built by it.
 */
class TestExerciseConfigLoader extends Tester\TestCase
{
    /** @var Loader */
    private $loader;

    public function __construct()
    {
        $this->loader = new Loader(new BoxService());
    }

    public function testTrue()
    {
        Assert::true(true);
    }

}

# Testing methods run
$testCase = new TestExerciseConfigLoader();
$testCase->run();
