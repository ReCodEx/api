<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

/**
 * Used in cooperation with group membership operations.
 */
class InvalidMembershipException extends ApiException
{
    /**
     * Create instance with further description
     * @param string $msg description
     * @param string $frontendErrorCode
     * @param null $frontendErrorParams
     */
    public function __construct(
        string $msg = 'check the API documentation for more information about membership',
        string $frontendErrorCode = FrontendErrorMappings::E400_000__BAD_REQUEST,
        $frontendErrorParams = null
    ) {
        parent::__construct(
            "Invalid Membership Request - $msg",
            IResponse::S400_BadRequest,
            $frontendErrorCode,
            $frontendErrorParams
        );
    }
}
