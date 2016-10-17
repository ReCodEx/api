<?php

namespace App\Exceptions;

/**
 * Connection to specified LDAP server failed and info cannot be retrieved.
 */
class LdapConnectException extends ApiException {
  /**
   * Creates instance with further description.
   * @param string $msg description
   */
  public function __construct(string $msg = 'Cannot connect to LDAP server. Please check your configuration.') {
    parent::__construct($msg);
  }

}
