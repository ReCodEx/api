<?php

namespace App\Exceptions; 

class HttpBasicAuthException extends UnauthorizedException {

  public function __construct($msg = "Invalid HTTP Basic authentication") {
    parent::__construct($msg);
  }

  public function getAdditionalHttpHeaders() {
    return array_merge(
      parent::getAdditionalHttpHeaders(),
      [ "WWW-Authenticate" => 'Basic realm="ReCodEx"' ]
    );
  }

}
