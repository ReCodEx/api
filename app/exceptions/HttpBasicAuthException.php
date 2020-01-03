<?php

namespace App\Exceptions;

/**
 * In some part of application HTTP basic auth can be used, in case
 * of bad credentials this exception can be thrown. Because of inheritance
 * this exception is treated as UnauthorizedException in most cases.
 */
class HttpBasicAuthException extends UnauthorizedException
{
    /**
     * Creates instance with given description.
     * @param string $msg description
     * @param string $frontendErrorCode
     */
    public function __construct(
        string $msg = "Invalid HTTP Basic authentication",
        string $frontendErrorCode = FrontendErrorMappings::E401_000__UNAUTHORIZED
    ) {
        parent::__construct($msg, $frontendErrorCode);
    }

    public function getAdditionalHttpHeaders()
    {
        return array_merge(
            parent::getAdditionalHttpHeaders(),
            ["WWW-Authenticate" => 'Basic realm="ReCodEx"']
        );
    }
}
