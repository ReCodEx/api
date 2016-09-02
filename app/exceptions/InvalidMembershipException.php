<?php

namespace App\Exception;
use Nette\Http\IResponse;

class InvalidMembershipException extends ApiException {
  public function __construct(string $msg = 'check the API documentation for more information about membership') {
    parent::__construct("Invalid Membership Request - $msg", IResponse::S400_BAD_REQUEST);
  }
}
