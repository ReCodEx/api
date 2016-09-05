<?php

namespace App\Exception;

class ResultsLoadingException extends SubmissionEvaluationFailedException {

  public function __construct($msg = 'Unexpected parsing error') {
    parent::__construct("Results loading or parsing failed - $msg");
  }

}
