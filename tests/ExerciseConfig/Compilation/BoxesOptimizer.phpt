<?php

include '../../bootstrap.php';

use App\Helpers\ExerciseConfig\Compilation\BoxesOptimizer;
use Tester\Assert;

/**
 * @testCase
 */
class TestBoxesOptimizer extends Tester\TestCase
{
  /** @var BoxesOptimizer */
  private $optimizer;

  public function __construct() {
    $this->optimizer = new BoxesOptimizer();
  }

  public function testTrue() {
    Assert::true(false);
    // @todo
  }

}

# Testing methods run
$testCase = new TestBoxesOptimizer();
$testCase->run();
