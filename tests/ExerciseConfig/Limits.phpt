<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;
use App\Helpers\ExerciseConfig\Limits;
use Symfony\Component\Yaml\Yaml;

class TestLimits extends Tester\TestCase
{

  /** @var Loader */
  private $builder;

  public function __construct() {
    $this->builder = new Loader;
  }

  public function testCorrect() {
    // @TODO
    Assert::true(TRUE);
  }

}

# Testing methods run
$testCase = new TestLimits;
$testCase->run();
