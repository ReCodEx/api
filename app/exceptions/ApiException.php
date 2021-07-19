<?php

namespace App\Exceptions;

use Exception;
use Nette\Http\IResponse;

/**
 * The great grandfather of almost all exceptions which can occur
 * in whole application. In addition to classical exceptions, this one adds
 * a bit of spices to the mix with custom defined error code and error
 * parameters.
 */
class ApiException extends Exception
{

    /** @var string */
    private $frontendErrorCode;
    /** @var array|null */
    private $frontendErrorParams;

    /**
     * Constructor.
     * @param string $msg Error message
     * @param int $code Error code
     * @param string $frontendErrorCode
     * @param array|null $frontendErrorParams
     * @param Exception $previous Previous exception
     */
    public function __construct(
        $msg = "Unexpected API error",
        $code = IResponse::S500_INTERNAL_SERVER_ERROR,
        $frontendErrorCode = FrontendErrorMappings::E500_000__INTERNAL_SERVER_ERROR,
        $frontendErrorParams = null,
        $previous = null
    ) {
        parent::__construct($msg, $code, $previous);
        $this->frontendErrorCode = $frontendErrorCode;
        $this->frontendErrorParams = $frontendErrorParams;
    }

    /**
     * Gets additional headers which should be added into http response.
     * @return array
     */
    public function getAdditionalHttpHeaders()
    {
        return [];
    }

    /**
     * Custom defined, far more fine-grained numeric exception code.
     * @return string
     */
    public function getFrontendErrorCode(): string
    {
        return $this->frontendErrorCode;
    }

    /**
     * Parameters which might be appended to error response.
     * @return mixed
     */
    public function getFrontendErrorParams()
    {
        return $this->frontendErrorParams;
    }
}
