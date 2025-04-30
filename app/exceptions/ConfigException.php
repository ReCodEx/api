<?php

namespace App\Exceptions;

use Exception;
use Nette\Http\IResponse;

/**
 * Exception concerning core module configuration.
 */
class ConfigException extends ApiException
{
    /**
     * Creates instance with further description.
     * @param string $msg description
     * @param Exception|null $previous
     * @param string $frontendErrorCode
     * @param array|null $frontendErrorParams
     */
    public function __construct(
        $msg,
        $previous = null,
        string $frontendErrorCode = FrontendErrorMappings::E500_000__INTERNAL_SERVER_ERROR,
        $frontendErrorParams = null
    ) {
        parent::__construct(
            "Internal configuration error - $msg",
            IResponse::S500_InternalServerError,
            $frontendErrorCode,
            $frontendErrorParams,
            $previous
        );
    }
}
