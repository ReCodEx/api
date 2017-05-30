<?php

include '../bootstrap.php';

use App\Helpers\ExerciseConfig\Loader;
use Tester\Assert;
use App\Helpers\ExerciseConfig\Transformer;


class TestExerciseConfigTransformer extends Tester\TestCase
{
  /** @var Transformer */
  private $transformer;

  public function __construct() {
    $this->transformer = new Transformer(new Loader);
  }

  public function testCorrect() {
    // @TODO: later when transformer is finished
    Assert::true(TRUE);
  }

}

# Testing methods run
$testCase = new TestExerciseConfigTransformer();
$testCase->run();
