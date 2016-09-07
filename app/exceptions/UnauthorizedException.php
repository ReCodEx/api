<?php

namespace App\Exceptions;
use Nette\Http\IResponse;

class UnauthorizedException extends ApiException {
  public function __construct($msg = "You must provide a valid access token or other specified means of authentication to be allowed to perform this request.") {
    parent::__construct($msg, IResponse::S401_UNAUTHORIZED);
  }

  public function getAdditionalHttpHeaders() {
    return array_merge(
      parent::getAdditionalHttpHeaders(),
      [ "WWW-Authenticate" => 'Bearer realm="ReCodEx"' ]
    );
  }
}
