<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

/**
 * Nice and easy purpose this exception truly has, sending wrong credentials
 * alerts to misguided users it must.
 */
class WrongCredentialsException extends ApiException
{
    /**
     * Creates instance with optional further description.
     * @param string $msg description
     * @param string $frontendErrorCode
     * @param array|null $frontendErrorParams
     */
    public function __construct(
        string $msg = "Invalid credentials",
        string $frontendErrorCode = FrontendErrorMappings::E400_100__WRONG_CREDENTIALS,
        $frontendErrorParams = null
    ) {
        parent::__construct($msg, IResponse::S400_BAD_REQUEST, $frontendErrorCode, $frontendErrorParams);
    }

    public function getAdditionalHttpHeaders()
    {
        return array_merge(
            parent::getAdditionalHttpHeaders(),
            ["WWW-Authenticate" => 'Bearer realm="ReCodEx"']
        );
    }
}
