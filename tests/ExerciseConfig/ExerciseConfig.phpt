<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;

class TestExerciseConfig extends Tester\TestCase
{
  static $config = [
    ""
  ];

  /** @var Loader */
  private $loader;

  public function __construct() {
    $this->loader = new Loader;
  }

  public function testSerialization() {
    // todo
    Assert::true(true);
  }

}

# Testing methods run
$testCase = new TestExerciseConfig;
$testCase->run();
