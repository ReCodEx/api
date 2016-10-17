<?php

namespace App\Exceptions;
use Nette\Http\IResponse;

/**
 * Used when requested resource was not found.
 */
class NotFoundException extends ApiException {
  /**
   * Creates instance with further description.
   * @param string $msg description
   */
  public function __construct(string $msg = 'The resource you requested was not found.') {
    parent::__construct("Not Found - $msg", IResponse::S404_NOT_FOUND);
  }
}
