<?php

namespace App\Exceptions;
use Nette\Http\IResponse;

/**
 * @todo not sure where this exception should or will be used
 */
class InvalidMembershipException extends ApiException {
  /**
   * Create instance with fruther description
   * @param string $msg description
   */
  public function __construct(string $msg = 'check the API documentation for more information about membership') {
    parent::__construct("Invalid Membership Request - $msg", IResponse::S400_BAD_REQUEST);
  }
}
