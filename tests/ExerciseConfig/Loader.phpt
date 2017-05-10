<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;


class TestExerciseConfigLoader extends Tester\TestCase
{
  /** @var Loader */
  private $loader;

  public function __construct() {
    $this->loader = new Loader;
  }

  public function testCorrect() {
    // @TODO
    Assert::true(TRUE);
  }

}

# Testing methods run
$testCase = new TestExerciseConfigLoader;
$testCase->run();
