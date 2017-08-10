<?php

include '../../bootstrap.php';

use App\Helpers\ExerciseConfig\Compilation\VariablesResolver;
use Tester\Assert;


class TestVariablesResolver extends Tester\TestCase
{
  /** @var VariablesResolver */
  private $resolver;

  public function __construct() {
    $this->resolver = null;
  }

  public function testTrue() {
    Assert::true(false);
  }

}

# Testing methods run
$testCase = new TestVariablesResolver();
$testCase->run();
