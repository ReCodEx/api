<?php

include '../../bootstrap.php';

use App\Helpers\ExerciseConfig\Pipeline;
use App\Helpers\ExerciseConfig\Validation\PipelineValidator;
use Tester\Assert;


/**
 * @testCase
 */
class TestPipelineValidator extends Tester\TestCase
{
  /** @var PipelineValidator */
  private $validator;

  protected function setUp() {
    $this->validator = new PipelineValidator();
  }

  public function testEmpty() {
    $pipeline = new Pipeline();

    Assert::noError(function () use ($pipeline) {
      $this->validator->validate($pipeline);
    });
  }

}

# Testing methods run
$testCase = new TestPipelineValidator;
$testCase->run();
