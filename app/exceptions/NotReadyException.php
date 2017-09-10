<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

class NotReadyException extends ApiException {

  public function __construct($msg = "The resource is not ready yet", $code = IResponse::S202_ACCEPTED, $previous = NULL) {
    parent::__construct($msg, $code, $previous);
  }
}
