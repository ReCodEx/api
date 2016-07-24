<?php

namespace App\Exception;
use Nette\Http\IResponse;

class InvalidAccessTokenException extends ApiException {

  /**
   * @param string $token   Access token from the HTTP request
   */
  public function __construct($token) {
      parent::__construct("Access token '$token' is not valid.", IResponse::S401_UNAUTHORIZED);
  }

}
