<?php

namespace App\Exceptions;

use Nette\Http\IResponse;
use Exception;

/**
 * Occurs when request data was somehow malformed or in bad format. Can be also
 * spotted in some cases of role checking errors. Proper 400 HTTP error code is
 * sent back to client.
 */
class BadRequestException extends ApiException
{
    /**
     * Create instance with textual description.
     * @param string $msg description
     * @param string $frontendErrorCode
     * @param array|null $frontendErrorParams
     * @param Exception $previous Previous exception
     */
    public function __construct(
        string $msg = 'one or more parameters are missing',
        string $frontendErrorCode = FrontendErrorMappings::E400_000__BAD_REQUEST,
        $frontendErrorParams = null,
        Exception $previous = null
    ) {
        parent::__construct(
            "Bad Request - $msg",
            IResponse::S400_BAD_REQUEST,
            $frontendErrorCode,
            $frontendErrorParams,
            $previous
        );
    }
}
