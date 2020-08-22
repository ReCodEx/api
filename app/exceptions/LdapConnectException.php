<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

/**
 * Connection to specified LDAP server failed and info cannot be retrieved.
 */
class LdapConnectException extends ApiException
{
    /**
     * Creates instance with further description.
     */
    public function __construct()
    {
        parent::__construct(
            'Cannot connect to LDAP server. Please check your configuration.',
            IResponse::S500_INTERNAL_SERVER_ERROR,
            FrontendErrorMappings::E500_002__LDAP_CONNECTION
        );
    }
}
