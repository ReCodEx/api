<?php

namespace App\Exception;

class LdapConnectException extends ApiException {
  public function __construct($msg = 'Cannot connect to LDAP server. Please check your configuration.') {
    parent::__construct($msg);
  }

}
