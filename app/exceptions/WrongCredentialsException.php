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
    parent::__construct($msg, IResponse::S400_BAD_REQUEST, FrontendErrorMappings::E400_100__WRONG_CREDENTIALS);
  }

  public function getAdditionalHttpHeaders() {
    return array_merge(
      parent::getAdditionalHttpHeaders(),
      [ "WWW-Authenticate" => 'Bearer realm="ReCodEx"' ]
    );
  }
}
