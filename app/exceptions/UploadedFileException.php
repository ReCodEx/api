<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

/**
 * Exception concerning uploaded files.
 */
class UploadedFileException extends ApiException {
  /**
   * Creates instance with further description.
   * @param string $msg description
   * @param null $previous
   * @param string $frontendErrorCode
   * @param null $frontendErrorParams
   */
  public function __construct(
    $msg,
    $previous = null,
    string $frontendErrorCode = FrontendErrorMappings::E500_000__INTERNAL_SERVER_ERROR,
    $frontendErrorParams = null
  ) {
    parent::__construct(
      "Uploaded files error - $msg",
      IResponse::S500_INTERNAL_SERVER_ERROR,
      $frontendErrorCode,
      $frontendErrorParams,
      $previous);
  }

}
