<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

/**
 * Nice and easy purpose this exception truly has, sending wrong credentials
 * alerts to misguided users it must.
 */
class WrongCredentialsException extends ApiException {
  /**
   * Creates instance with optional further description.
   * @param string $msg description
   */
  public function __construct(string $msg = "The username or password is incorrect.") {
    parent::__construct($msg, IResponse::S401_UNAUTHORIZED);
  }

  public function getAdditionalHttpHeaders() {
    return array_merge(
      parent::getAdditionalHttpHeaders(),
      [ "WWW-Authenticate" => 'Bearer realm="ReCodEx"' ]
    );
  }
}
