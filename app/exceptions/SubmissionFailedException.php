<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

/**
 * Thrown if user submission failed and cannot be performed.
 */
class SubmissionFailedException extends ApiException
{
    /**
     * Creates instance with further description.
     * @param string $msg description
     * @param string $frontendErrorCode
     * @param null $frontendErrorParams
     */
    public function __construct(
        string $msg = 'Unexpected server error',
        string $frontendErrorCode = FrontendErrorMappings::E500_000__INTERNAL_SERVER_ERROR,
        $frontendErrorParams = null
    ) {
        parent::__construct(
            "Submission Failed - $msg",
            IResponse::S500_InternalServerError,
            $frontendErrorCode,
            $frontendErrorParams
        );
    }
}
