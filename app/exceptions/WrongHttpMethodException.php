<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

/**
 * Thrown if request was directed to non-implemented HTTP method.
 * 400 HTTP error code sent back.
 */
class WrongHttpMethodException extends ApiException
{
    /**
     * Create instance with defined method which is not supported.
     * @param string $method HTTP method of the request
     */
    public function __construct(string $method)
    {
        $method = strtoupper($method);
        parent::__construct(
            "This endpoint does not respond to $method HTTP requests, check the API documentation for more information.",
            IResponse::S405_METHOD_NOT_ALLOWED,
            FrontendErrorMappings::E405_000__METHOD_NOT_ALLOWED,
            ["method" => $method]
        );
    }
}
