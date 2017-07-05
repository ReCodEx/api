<?php

include '../../bootstrap.php';

use App\Helpers\ExerciseConfig\VariableFactory;
use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;

class TestPipeline extends Tester\TestCase
{
  /** @var Loader */
  private $loader;

  public function __construct() {
    $this->loader = new Loader(new VariableFactory());
  }

  public function testCorrect() {
    Assert::true(false);
  }

}

# Testing methods run
$testCase = new TestPipeline;
$testCase->run();
