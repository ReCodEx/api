<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

/**
 * Used when JWT decoding of given access token failed miserably.
 */
class InvalidExternalTokenException extends ApiException
{
    /**
     * Creates instance with invalid token as argument
     * @param string $token Access token from the HTTP request
     * @param string $reason Additional (detailed) message (what went wrong).
     * @param \Exception|null $previous
     */
    public function __construct(string $token, string $reason = '', $previous = null)
    {
        $message = "External token '$token' is not valid.";
        if ($reason) {
            $message .= " $reason";
        }

        parent::__construct(
            $message,
            IResponse::S401_UNAUTHORIZED,
            FrontendErrorMappings::E401_002__INVALID_TOKEN,
            ["token" => $token],
            $previous
        );
    }
}
