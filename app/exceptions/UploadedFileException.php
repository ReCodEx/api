<?php

namespace App\Exceptions;

/**
 * Exception concerning uploaded files.
 */
class UploadedFileException extends ApiException {
  /**
   * Creates instance with further description.
   * @param string $msg description
   */
  public function __construct($msg, $previous = NULL) {
    parent::__construct("Uploaded files error - $msg", 500, $previous);
  }

}
