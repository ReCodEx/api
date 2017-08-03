<?php

include '../bootstrap.php';

use App\Helpers\ExerciseConfig\Compilation\BoxesCompiler;
use Tester\Assert;


class TestBoxesCompiler extends Tester\TestCase
{
  /** @var BoxesCompiler */
  private $compiler;

  public function __construct() {
    $this->compiler = null;
  }

  public function testTrue() {
    Assert::true(false);
  }

}

# Testing methods run
$testCase = new TestBoxesCompiler();
$testCase->run();
