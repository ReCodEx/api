<?php

namespace App\Exception;
use Nette\Http\IResponse;

class WrongCredentialsException extends ApiException {
  public function __construct() {
    parent::__construct("The username or password is incorrect.", IResponse::S400_BAD_REQUEST);
  }
}
