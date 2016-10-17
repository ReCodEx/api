<?php

namespace App\Exceptions;

/**
 * Thrown if some of the CAS LDAP attributes was not found.
 */
class CASMissingInfoException extends ApiException {
  /**
   * Create instance with textual description.
   * @param string $msg description
   */
  public function __construct(string $msg = "Reading LDAP attribute failed") {
    parent::__construct($msg);
  }
}
