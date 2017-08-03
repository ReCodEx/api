<?php

include '../bootstrap.php';

use App\Helpers\ExerciseConfig\Compilation\PipelinesMerger;
use Tester\Assert;


class TestPipelinesMerger extends Tester\TestCase
{
  /** @var PipelinesMerger */
  private $merger;

  public function __construct() {
    $this->merger = null;
  }

  public function testTrue() {
    Assert::true(false);
  }

}

# Testing methods run
$testCase = new TestPipelinesMerger();
$testCase->run();
