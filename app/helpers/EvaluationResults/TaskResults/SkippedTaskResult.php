<?php

namespace App\Helpers\EvaluationResults;
use App\Exceptions\ResultsLoadingException;

class SkippedTaskResult extends TaskResult {

  public function __construct(string $id) {
    parent::__construct([
      "task-id" => $id,
      "status" => self::STATUS_SKIPPED
    ]);
  }

}
