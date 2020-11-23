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
     * @param string $name Name of the file which cannot be received
     * @param int $code PHP error code
     * @param string $frontendCode
     * @param string $message
     */
    public function __construct(
        string $name,
        int $code = 0,
        string $frontendCode = FrontendErrorMappings::E500_001__CANNOT_RECEIVE_FILE,
        string $message = "Cannot receive uploaded file '%s' due to '%d'"
    ) {
        parent::__construct(
            sprintf($message, $name, $code),
            IResponse::S500_INTERNAL_SERVER_ERROR,
            $frontendCode,
            ["filename" => $name, "errorCode" => $code]
        );
    }
}
