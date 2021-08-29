<?php

namespace App\Exceptions;

use Nette\Http\IResponse;
use Exception;

/**
 * Used when requested resource was not found.
 */
class NotFoundException extends ApiException
{
    /**
     * Creates instance with further description.
     * @param string $msg description
     * @param string $frontendErrorCode
     * @param array|null $frontendErrorParams
     * @param Exception $previous Previous exception
     */
    public function __construct(
        string $msg = 'The resource you requested was not found.',
        string $frontendErrorCode = FrontendErrorMappings::E404_000__NOT_FOUND,
        $frontendErrorParams = null,
        $previous = null
    ) {
        parent::__construct(
            "Not Found - $msg",
            IResponse::S404_NOT_FOUND,
            $frontendErrorCode,
            $frontendErrorParams,
            $previous
        );
    }
}
