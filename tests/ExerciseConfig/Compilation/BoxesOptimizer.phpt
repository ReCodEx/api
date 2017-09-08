<?php

include '../../bootstrap.php';

use App\Helpers\ExerciseConfig\Compilation\BoxesOptimizer;
use Tester\Assert;


class TestBoxesOptimizer extends Tester\TestCase
{
  /** @var BoxesOptimizer */
  private $optimizer;

  public function __construct() {
    $this->optimizer = null;
  }

  public function testTrue() {
    Assert::true(true);
    // @todo
  }

}

# Testing methods run
$testCase = new TestBoxesOptimizer();
$testCase->run();
