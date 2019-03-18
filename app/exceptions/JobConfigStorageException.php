<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

/**
 * Job configuration Storage helper uses this exception to express
 * tiny major errors.
 */
class JobConfigStorageException extends ApiException {
  /**
   * Creates instance with further description.
   * @param string $msg description
   * @param string $frontendErrorCode
   * @param null $frontendErrorParams
   */
  public function __construct(
    $msg = 'Job config could not have been stored or loaded',
    string $frontendErrorCode = FrontendErrorMappings::E500_100__JOB_CONFIG,
    $frontendErrorParams = null
  ) {
    parent::__construct("Job configuration storage error - $msg", IResponse::S500_INTERNAL_SERVER_ERROR, $frontendErrorCode, $frontendErrorParams);
  }

}
