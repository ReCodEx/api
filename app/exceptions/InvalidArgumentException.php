<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

/**
 * Used if there is something very wrong with some particular parameter.
 * It may be wrong type or missing argument or something similar.
 */
class InvalidArgumentException extends ApiException
{
    /**
     * Creates exception with invalid argument name and some further description.
     * @param string $argument name of argument
     * @param string $msg further message
     * @param string $frontendErrorCode
     */
    public function __construct(
        string $argument,
        string $msg = 'check the API documentation for more information about validation rules',
        string $frontendErrorCode = FrontendErrorMappings::E400_000__BAD_REQUEST
    ) {
        parent::__construct(
            "Invalid Argument '$argument' - $msg",
            IResponse::S400_BAD_REQUEST,
            $frontendErrorCode,
            ["argument" => $argument]
        );
    }
}
