<?php

namespace App\Exceptions;
use Nette\Http\IResponse;

/**
 * "User is not allowed to do such thing", if this is the thought it just came
 * to your mind during coding, just throw this exception and
 * everything should be fine.
 */
class ForbiddenRequestException extends ApiException {
  /**
   * Create with further description and optionally HTTP error code.
   * @param string $msg description
   * @param int $code HTTP error code, defaulted to 403
   */
  public function __construct(string $msg = "Forbidden Request - Access denied", $code = IResponse::S403_FORBIDDEN) {
    parent::__construct($msg, $code);
  }
}
