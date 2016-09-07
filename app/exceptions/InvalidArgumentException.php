<?php

namespace App\Exceptions;
use Nette\Http\IResponse;

class InvalidArgumentException extends ApiException {
  public function __construct(string $arg, string $msg = 'check the API documentation for more information about validation rules') {
    parent::__construct("Invalid Argument '$arg' - $msg", IResponse::S400_BAD_REQUEST);
  }
}
