<?php

namespace App\Helpers\EvaluationResults;

use App\Helpers\JobConfig\TestConfig;

/**
 * Test result implementation for skipped tests
 */
class SkippedTestResult extends UnsuccessfulTestResult {

  /**
   * Constructor
   * @param TestConfig $config Configuration of skipped test
   */
  public function __construct(TestConfig $config) {
    parent::__construct($config, TestResult::STATUS_SKIPPED);
  }

}
