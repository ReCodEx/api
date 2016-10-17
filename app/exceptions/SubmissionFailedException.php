<?php

namespace App\Exceptions;
use Nette\Http\IResponse;

/**
 * Thrown if user submission failed and cannot be performed.
 */
class SubmissionFailedException extends ApiException {
  /**
   * Creates instance with further description.
   * @param string $msg description
   */
  public function __construct(string $msg = 'Unexpected server error') {
    parent::__construct("Submission Failed - $msg", IResponse::S500_INTERNAL_SERVER_ERROR);
  }
}
