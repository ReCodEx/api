<?php

namespace App\Exceptions;
use Nette\Http\IResponse;

/**
 * Occurs when request data was somehow malformed or in bad format. Can be also
 * spotted in some cases of role checking errors. Proper 400 HTTP error code is
 * sent back to client.
 */
class BadRequestException extends ApiException {
  /**
   * Create instance with textual description.
   * @param string $msg description
   */
  public function __construct(string $msg = 'one or more parameters are missing') {
    parent::__construct("Bad Request - $msg", IResponse::S400_BAD_REQUEST);
  }
}
