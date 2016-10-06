<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

class ForbiddenRequestException extends ApiException {
  public function __construct($msg = "Forbidden Request - Access denied", $code = IResponse::S403_FORBIDDEN) {
    parent::__construct($msg, $code);
  }
}
