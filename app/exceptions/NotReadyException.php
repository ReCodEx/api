<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

class NotReadyException extends ApiException
{

    public function __construct(
        $msg = "The resource is not ready yet",
        $code = IResponse::S202_ACCEPTED,
        string $frontendErrorCode = FrontendErrorMappings::E202_000__ACCEPTED,
        $frontendErrorParams = null,
        $previous = null
    ) {
        parent::__construct($msg, $code, $frontendErrorCode, $frontendErrorParams, $previous);
    }
}
