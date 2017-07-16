<?php

include '../../bootstrap.php';

use Tester\Assert;


class TestPipelineValidator extends Tester\TestCase
{

  public function testCorrect() {
    Assert::true(true);
    // @todo
  }

}

# Testing methods run
$testCase = new TestPipelineValidator;
$testCase->run();
