<?php

namespace App\Exception;

use Nette\Http\IResponse;

class InternalServerErrorException extends ApiException {
  public function __construct($details = 'please contact the administrator of the service') {
    parent::__construct("Internal Server Error - $details", IResponse::S500_INTERNAL_SERVER_ERROR);
  }
}
