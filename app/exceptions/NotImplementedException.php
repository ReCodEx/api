<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

/**
 * Actually not used for debbuging purposes but used in production and thrown
 * if user requested non-existing application route.
 */
class NotImplementedException extends ApiException
{
    /**
     * Simple constructor with no parameters.
     */
    public function __construct()
    {
        parent::__construct(
            "This feature is not implemented. Contact the authors of the API for more information about the status of the API.",
            IResponse::S501_NOT_IMPLEMENTED,
            FrontendErrorMappings::E501_000__NOT_IMPLEMENTED
        );
    }
}
