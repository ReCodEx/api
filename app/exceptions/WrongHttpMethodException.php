<?php

namespace App\Exceptions;
use Nette\Http\IResponse;

class WrongHttpMethodException extends ApiException {

  /**
   * @param string    $method  HTTP method of the request
   */
  public function __construct($method) {
    $method = strtoupper($method);
    parent::__construct("This endpoint does not respond to $method HTTP requests, check the API documentation for more information.", IResponse::S400_BAD_REQUEST);
  }

}
