<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

/**
 * User requested action which requires access token but it was not provided
 * at all. Proper 401 HTTP response error code sent back to client.
 */
class NoAccessTokenException extends ApiException
{
    /**
     * No need for defining anything, just create it.
     */
    public function __construct()
    {
        parent::__construct(
            "You must provide an access token for this action.",
            IResponse::S401_UNAUTHORIZED,
            FrontendErrorMappings::E401_001__NO_TOKEN
        );
    }

    public function getAdditionalHttpHeaders()
    {
        return array_merge(
            parent::getAdditionalHttpHeaders(),
            ["WWW-Authenticate" => 'Bearer realm="ReCodEx"']
        );
    }
}
