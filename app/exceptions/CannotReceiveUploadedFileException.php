<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

/**
 * In case of file not uploaded properly this exception may be thrown.
 * HTTP error code number 500 is sent back.
 */
class CannotReceiveUploadedFileException extends ApiException
{

    /**
     * Creates instance with provided file name.
     * @param string $message
     * @param int $code HTTP response code
     * @param string $frontendCode
     */
    public function __construct(
        string $message,
        int $code = IResponse::S500_INTERNAL_SERVER_ERROR,
        string $frontendCode = FrontendErrorMappings::E500_001__CANNOT_RECEIVE_FILE,
        $frontendErrorParams = null
    ) {
        parent::__construct($message, $code, $frontendCode, $frontendErrorParams);
    }
}
