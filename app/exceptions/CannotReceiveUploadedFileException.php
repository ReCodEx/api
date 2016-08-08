<?php

namespace App\Exception;

use Nette\Http\IResponse;

class CannotReceiveUploadedFileException extends ApiException {

  /**
   * @param string $name  Name of the file which cannot be received
   */
  public function __construct(string $name) {
    parent::__construct("Cannot receive uploaded file '$name'", IResponse::S500_INTERNAL_SERVER_ERROR);
  }

}
