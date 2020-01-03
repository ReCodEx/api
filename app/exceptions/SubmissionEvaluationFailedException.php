<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

/**
 * Retrieving or evaluation of results of a submission failed miserably.
 */
class SubmissionEvaluationFailedException extends ApiException
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
            "Submission Evaluation Failed - $msg",
            IResponse::S500_INTERNAL_SERVER_ERROR,
            $frontendErrorCode,
            $frontendErrorParams
        );
    }
}
