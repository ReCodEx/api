<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

/**
 * General exception used in all job configuration helpers in case
 * of loading error. Usually concerning bad structure or bad value type.
 */
class JobConfigLoadingException extends ApiException {
  /**
   * Create instance with further description.
   * @param string $msg description
   * @param string $frontendErrorCode
   * @param null $frontendErrorParams
   */
  public function __construct(
    string $msg = 'Please contact your supervisor',
    string $frontendErrorCode = FrontendErrorMappings::E400_100__JOB_CONFIG,
    $frontendErrorParams = null
  ) {
    parent::__construct("Job configuration file cannot be opened or parsed - $msg", IResponse::S400_BAD_REQUEST, $frontendErrorCode, $frontendErrorParams);
  }
}
