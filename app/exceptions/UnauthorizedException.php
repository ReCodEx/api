<?php

namespace App\Exceptions;
use Nette\Http\IResponse;

/**
 * Occurs if user did not give a valid access token during HTTP request.
 */
class UnauthorizedException extends ApiException {
  /**
   * Creates instance with further description.
   * @param string $msg
   */
  public function __construct(string $msg = "You must provide a valid access token or other specified means of authentication to be allowed to perform this request.") {
    parent::__construct($msg, IResponse::S401_UNAUTHORIZED);
  }

  public function getAdditionalHttpHeaders() {
    return array_merge(
      parent::getAdditionalHttpHeaders(),
      [ "WWW-Authenticate" => 'Bearer realm="ReCodEx"' ]
    );
  }
}
