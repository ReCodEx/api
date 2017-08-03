<?php

include '../bootstrap.php';

use App\Helpers\ExerciseConfig\Compilation\TestBoxesOptimizer;
use Tester\Assert;


class TestTestBoxesOptimizer extends Tester\TestCase
{
  /** @var TestBoxesOptimizer */
  private $optimizer;

  public function __construct() {
    $this->optimizer = null;
  }

  public function testTrue() {
    Assert::true(false);
  }

}

# Testing methods run
$testCase = new TestTestBoxesOptimizer();
$testCase->run();
