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
   */
  public function __construct($msg, $previous = NULL) {
    parent::__construct("Uploaded files error - $msg", IResponse::S500_INTERNAL_SERVER_ERROR, $previous);
  }

}
