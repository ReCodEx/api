<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

/**
 * Some parts of system are in invalid state and due to this request cannot be performed.
 */
class InvalidStateException extends ApiException
{
    /**
     * Create exception with some message.
     * @param string $msg message
     * @param string $frontendErrorCode
     * @param null $frontendErrorParams
     */
    public function __construct(
        string $msg = 'check the API documentation for more information about validation rules',
        string $frontendErrorCode = FrontendErrorMappings::E500_000__INTERNAL_SERVER_ERROR,
        $frontendErrorParams = null
    ) {
        parent::__construct(
            "Invalid State - $msg",
            IResponse::S500_InternalServerError,
            $frontendErrorCode,
            $frontendErrorParams
        );
    }
}
