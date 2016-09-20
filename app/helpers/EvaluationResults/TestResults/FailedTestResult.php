<?php

namespace App\Helpers\EvaluationResults;

use App\Helpers\JobConfig\TestConfig;

class FailedTestResult extends UnsuccessfulTestResult {

  public function __construct(TestConfig $config) {
    parent::__construct($config, TestResult::STATUS_FAILED);
  }

}
