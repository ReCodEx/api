<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\ExerciseConfig\Transformer;

/**
 * Exercise configuration builder is mostly tested in components which are constructed/built by it.
 * This is only general test which tests only simple cases.
 */
class TestExerciseConfigTransformer extends Tester\TestCase
{
  /** @var Transformer */
  private $transformer;

  public function __construct() {
    $this->transformer = new Transformer;
  }

  public function testCorrect() {
    // @TODO: later when transformer is finished
    Assert::true(TRUE);
  }

}

# Testing methods run
$testCase = new TestExerciseConfigTransformer();
$testCase->run();
