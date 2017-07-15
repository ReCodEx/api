<?php

include '../../bootstrap.php';

use Tester\Assert;


class TestExerciseLimitsValidator extends Tester\TestCase
{

  public function testCorrect() {
    Assert::true(true);
    // @todo
  }

}

# Testing methods run
$testCase = new TestExerciseLimitsValidator;
$testCase->run();
