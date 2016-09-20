<?php

namespace App\Helpers\EvaluationResults;

use App\Helpers\JobConfig\TestConfig;

class SkippedTestResult extends UnsuccessfulTestResult {

  public function __construct(TestConfig $config) {
    parent::__construct($config, TestResult::STATUS_SKIPPED);
  }

}
