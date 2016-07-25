<?php

namespace App\Exception;

use Nette\Http\IResponse;

class ForbiddenRequestException extends ApiException {
  public function __construct() {
    parent::__construct('Forbidden Request - Access denied', IResponse::S403_FORBIDDEN);
  }
}
