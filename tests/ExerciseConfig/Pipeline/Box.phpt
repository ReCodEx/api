<?php

include '../../bootstrap.php';

use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;

class TestBox extends Tester\TestCase
{
  /** @var Loader */
  private $loader;

  public function __construct() {
    $this->loader = new Loader;
  }

  public function testCorrect() {
    Assert::true(false);
  }

}

# Testing methods run
$testCase = new TestBox;
$testCase->run();
