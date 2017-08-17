<?php

include '../../bootstrap.php';

use App\Helpers\ExerciseConfig\Compilation\TestDirectoriesResolver;
use Tester\Assert;


class TestTestDirectoriesResolver extends Tester\TestCase
{
  /** @var TestDirectoriesResolver */
  private $resolver;

  public function __construct() {
    $this->resolver = new TestDirectoriesResolver();
  }

  public function testTrue() {
    Assert::true(false);
  }

}

# Testing methods run
$testCase = new TestTestDirectoriesResolver();
$testCase->run();
