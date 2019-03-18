<?php

namespace App\Exceptions;
use Nette\Http\IResponse;

/**
 * In case of file not uploaded properly this exception may be thrown.
 * HTTP error code number 500 is sent back.
 */
class CannotReceiveUploadedFileException extends ApiException {

  /**
   * Creates instance with provided file name.
   * @param string $name  Name of the file which cannot be received
   */
  public function __construct(string $name) {
    parent::__construct(
      "Cannot receive uploaded file '$name'",
      IResponse::S500_INTERNAL_SERVER_ERROR,
      FrontendErrorMappings::E500_001__CANNOT_RECEIVE_FILE,
      [ "filename" => $name ]
    );
  }

}
