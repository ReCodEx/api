<?php

namespace App\Exceptions;
use Nette\Http\IResponse;

class NoAccessTokenException extends ApiException {
  public function __construct() {
    parent::__construct("You must provide an access token for this action.", IResponse::S401_UNAUTHORIZED);
  }

  public function getAdditionalHttpHeaders() {
    return array_merge(
      parent::getAdditionalHttpHeaders(),
      [ "WWW-Authenticate" => 'Bearer realm="ReCodEx"' ]
    );
  }
}
