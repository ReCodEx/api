<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

/**
 * Thrown if results cannot be properly loaded/parsed from worker's
 * uploaded files.
 */
class ResultsLoadingException extends SubmissionEvaluationFailedException {
  /**
   * Creates instance with further description.
   * @param string $msg description
   */
  public function __construct(string $msg = 'Unexpected parsing error') {
    parent::__construct("Results loading or parsing failed - $msg");
  }
}
