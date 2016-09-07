<?php

namespace App\Exceptions;
use Nette\Http\IResponse;

class NotImplementedException extends ApiException {

  public function __construct() {
    parent::__construct("This feature is not implemented. Contact the authors of the API for more information about the status of the API.", IResponse::S501_NOT_IMPLEMENTED);
  }

}
