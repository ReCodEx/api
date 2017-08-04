<?php

include '../../bootstrap.php';

use App\Helpers\ExerciseConfig\Compilation\BoxesSorter;
use Tester\Assert;


class TestBoxesSorter extends Tester\TestCase
{
  /** @var BoxesSorter */
  private $sorter;

  public function __construct() {
    $this->sorter = null;
  }

  public function testTrue() {
    Assert::true(false);
  }

}

# Testing methods run
$testCase = new TestBoxesSorter();
$testCase->run();
