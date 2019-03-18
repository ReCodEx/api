<?php

namespace App\Exceptions;

/**
 * Thrown if results cannot be properly loaded/parsed from worker's
 * uploaded files.
 */
class ResultsLoadingException extends SubmissionEvaluationFailedException {
  /**
   * Creates instance with further description.
   * @param string $msg description
   * @param string $frontendErrorCode
   * @param null $frontendErrorParams
   */
  public function __construct(
    string $msg = 'Unexpected parsing error',
    string $frontendErrorCode = FrontendErrorMappings::E500_000__INTERNAL_SERVER_ERROR,
    $frontendErrorParams = null
  ) {
    parent::__construct("Results loading or parsing failed - $msg", $frontendErrorCode, $frontendErrorParams);
  }
}
