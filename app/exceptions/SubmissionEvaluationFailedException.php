<?php

namespace App\Exceptions;
use Nette\Http\IResponse;

class SubmissionEvaluationFailedException extends ApiException {

  public function __construct($msg = 'Unexpected server error') {
    parent::__construct("Submission Evaluation Failed - $msg", IResponse::S500_INTERNAL_SERVER_ERROR);
  }

}
