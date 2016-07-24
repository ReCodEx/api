<?php

namespace App\Exception;
use Nette\Http\IResponse;

class BadRequestException extends ApiException {
  public function __construct(string $msg = 'one or more parameters are missing') {
    parent::__construct("Bad Request - $msg", IResponse::S400_BAD_REQUEST);
  }
}
