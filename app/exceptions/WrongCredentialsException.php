<?php

namespace App\Exceptions;
use Nette\Http\IResponse;

class WrongCredentialsException extends ApiException {
  public function __construct($msg = "The username or password is incorrect.") {
    parent::__construct($msg, IResponse::S401_UNAUTHORIZED);
  }

  public function getAdditionalHttpHeaders() {
    return array_merge(
      parent::getAdditionalHttpHeaders(),
      [ "WWW-Authenticate" => 'Bearer realm="ReCodEx"' ]
    );
  }
}
