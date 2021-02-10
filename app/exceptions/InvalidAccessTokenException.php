<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

/**
 * Used when JWT decoding of given access token failed miserably.
 */
class InvalidAccessTokenException extends ApiException
{
    /**
     * Creates instance with invalid token as argument
     * @param string $token Access token from the HTTP request
     */
    public function __construct($token, $previous = null)
    {
        parent::__construct(
            "Access token '$token' is not valid.",
            IResponse::S401_UNAUTHORIZED,
            FrontendErrorMappings::E401_002__INVALID_TOKEN,
            ["token" => $token],
            $previous
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
