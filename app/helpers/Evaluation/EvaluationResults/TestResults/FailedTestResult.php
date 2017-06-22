<?php

namespace App\Helpers\EvaluationResults;

use App\Helpers\JobConfig\TestConfig;

/**
 * Test result implementation for failed tests
 */
class FailedTestResult extends UnsuccessfulTestResult {

  /**
   * Constructor
   * @param TestConfig $config Configuration of failed test
   */
  public function __construct(TestConfig $config) {
    parent::__construct($config, TestResult::STATUS_FAILED);
  }

}
