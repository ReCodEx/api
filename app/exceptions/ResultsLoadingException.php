<?php

namespace App\Exceptions;

class ResultsLoadingException extends SubmissionEvaluationFailedException {

  public function __construct($msg = 'Unexpected parsing error') {
    parent::__construct("Results loading or parsing failed - $msg");
  }

}
