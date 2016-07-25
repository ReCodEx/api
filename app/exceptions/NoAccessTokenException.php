<?php

namespace App\Exception;
use Nette\Http\IResponse;

class NoAccessTokenException extends ApiException {
  public function __construct() {
    parent::__construct("You must provide an access token for this action.", IResponse::S400_BAD_REQUEST);
  }
}
