<?php

namespace App\Exceptions;
use Nette\Http\IResponse;

/**
 * Retrieving or evaluation of results of a submission failed misserably.
 */
class SubmissionEvaluationFailedException extends ApiException {
  /**
   * Creates instance with further description.
   * @param string $msg description
   */
  public function __construct(string $msg = 'Unexpected server error') {
    parent::__construct("Submission Evaluation Failed - $msg", IResponse::S500_INTERNAL_SERVER_ERROR);
  }
}
