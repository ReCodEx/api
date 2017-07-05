<?php

include '../bootstrap.php';

use App\Helpers\ExerciseConfig\VariableFactory;
use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;

/**
 * Exercise configuration builder is mostly tested in components which are constructed/built by it.
 * This is only general test which tests only simple cases.
 */
class TestExerciseConfigLoader extends Tester\TestCase
{
  /** @var Loader */
  private $loader;

  public function __construct() {
    $this->loader = new Loader;
  }

  public function testCorrect() {
    // @TODO: later when loader is finished
    Assert::true(TRUE);
  }

}

# Testing methods run
$testCase = new TestExerciseConfigLoader;
$testCase->run();
