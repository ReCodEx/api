<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\ExerciseConfig\Loader;

class TestTest extends Tester\TestCase
{
  static $config = [
    ""
  ];

  /** @var Loader */
  private $loader;

  public function __construct() {
    $this->loader = new Loader;
  }

  public function testSerialization() {
    // todo
    Assert::true(true);
  }

}

# Testing methods run
$testCase = new TestTest;
$testCase->run();
