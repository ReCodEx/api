<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

/**
 * Thrown if some of the CAS LDAP attributes was not found.
 */
class CASMissingInfoException extends ApiException
{
    /**
     * Create instance with textual description.
     * @param string $msg description
     * @param string $frontendErrorCode
     * @param null $frontendErrorParams
     */
    public function __construct(
        string $msg = "Reading CAS attribute failed",
        string $frontendErrorCode = FrontendErrorMappings::E409_000__CONFLICT,
        $frontendErrorParams = null
    ) {
        parent::__construct($msg, IResponse::S409_CONFLICT, $frontendErrorCode, $frontendErrorParams);
    }
}
