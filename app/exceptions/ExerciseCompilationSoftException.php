<?php

namespace App\Exceptions;

use Nette\Http\IResponse;

/**
 * Exception used in exercise compilation to job configuration.
 * Used if user inputs are incorrect or invalid (e.g. uploaded files).
 */
class ExerciseCompilationSoftException extends ExerciseCompilationException
{

    /**
     * Constructor.
     * @param string $msg description
     * @param string $frontendErrorCode
     * @param array|null $frontendErrorParams
     */
    public function __construct(
        string $msg = 'Please, check the exercise instructions',
        string $frontendErrorCode = FrontendErrorMappings::E400_400__EXERCISE_COMPILATION,
        $frontendErrorParams = null
    ) {
        parent::__construct($msg, IResponse::S400_BAD_REQUEST, $frontendErrorCode, $frontendErrorParams);
    }
}
