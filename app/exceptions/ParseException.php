<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

/**
 * General exception used in all yaml or json loading.
 */
class ParseException extends ApiException
{
    /**
     * Create instance with further description.
     * @param string $msg description
     * @param string $frontendErrorCode
     * @param null $frontendErrorParams
     */
    public function __construct(
        string $msg = 'Please contact system administrator',
        string $frontendErrorCode = FrontendErrorMappings::E400_000__BAD_REQUEST,
        $frontendErrorParams = null
    ) {
        parent::__construct(
            "Parsing error - $msg",
            IResponse::S400_BadRequest,
            $frontendErrorCode,
            $frontendErrorParams
        );
    }
}
