<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ApiException;
use App\Exceptions\FrontendErrorMappings;
use App\Helpers\UserActions;
use App\Presenters\BasePresenter;
use App\Security\UserStorage;
use Nette\Http\IResponse;
use Nette\Application\BadRequestException;
use Nette\Application\AbortException;
use Doctrine\DBAL\Exception\ConnectionException;
use Tracy\ILogger;
use Exception;
use Throwable;

/**
 * The error presenter for the API module - all responses are served as JSONs with a fixed format.
 */
class ApiErrorPresenter extends BasePresenter
{
    /**
     * @var ILogger
     * @inject
     */
    public $logger;

    /**
     * @var UserActions
     * @inject
     */
    public $userActions;

    /**
     * @param Exception $exception
     * @return void
     * @throws AbortException
     */
    public function renderDefault($exception)
    {
        // first let us log the whole error thingy
        $this->handleLogging($exception);

        if ($exception instanceof ApiException) {
            $this->handleAPIException($exception);
        } elseif ($exception instanceof BadRequestException) {
            $this->sendErrorResponse(
                $exception->getCode(),
                "Bad Request",
                FrontendErrorMappings::E400_000__BAD_REQUEST
            );
        } elseif ($exception instanceof ConnectionException) {
            $this->sendErrorResponse(IResponse::S500_InternalServerError, "Database is offline");
        } else {
            $type = get_class($exception);
            $this->sendErrorResponse(IResponse::S500_InternalServerError, "Unexpected Error {$type}");
        }
    }

    /**
     * Send an error response based on a known type of exceptions - derived from ApiException
     * @param ApiException $exception The exception which caused the error
     * @throws AbortException
     */
    public function handleAPIException(ApiException $exception)
    {
        $res = $this->getHttpResponse();
        $additionalHeaders = $exception->getAdditionalHttpHeaders();
        foreach ($additionalHeaders as $name => $value) {
            $res->setHeader($name, $value);
        }
        $this->sendErrorResponse(
            $exception->getCode(),
            $exception->getMessage(),
            $exception->getFrontendErrorCode(),
            $exception->getFrontendErrorParams()
        );
    }

    /**
     * Simply logs given exception into standard logger. Some filtering or
     * further modifications can be engaged.
     * @param Throwable $ex Exception which should be logged
     */
    public function handleLogging(Throwable $ex)
    {
        if ($ex instanceof BadRequestException) {
            // nothing to log here
        } else {
            if ($ex instanceof ApiException && $ex->getCode() < 500) {
                $this->logger->log(
                    "HTTP code {$ex->getCode()}: {$ex->getMessage()} in {$ex->getFile()}:{$ex->getLine()}",
                    'access'
                );
            } else {
                $this->logger->log($ex, ILogger::EXCEPTION);
            }
        }
    }

    /**
     * Send a JSON response with a specific HTTP code
     * @param int $code HTTP code of the response
     * @param string $msg Human readable description of the error
     * @param string $frontendErrorCode custom defined, far more fine-grained exception code
     * @param mixed $frontendErrorParams parameters belonging to error
     * @return void
     * @throws AbortException
     */
    protected function sendErrorResponse(
        int $code,
        string $msg,
        string $frontendErrorCode = FrontendErrorMappings::E500_000__INTERNAL_SERVER_ERROR,
        $frontendErrorParams = null
    ) {
        // calling user->isLoggedIn results in throwing exception in case of
        // invalid token (after update to nette/security:v3.1), therefore we
        // need to call our UserStorage directly
        /** @var UserStorage */
        $storage = $this->getUser()->getStorage();
        if ($storage->isAuthenticated()) {
            // log the action done by the current user
            // determine the action name from the application request
            $req = $this->getRequest();
            $remoteAddr = $this->getHttpRequest()->getRemoteAddress();
            $params = $req->getParameters();
            $action = isset($params[self::ACTION_KEY]) ? $params[self::ACTION_KEY] : self::DEFAULT_ACTION;
            unset($params[self::ACTION_KEY]);
            $fullyQualified = ':' . $req->getPresenterName() . ':' . $action;

            try {
                $this->userActions->log($fullyQualified, $remoteAddr, $params, $code, $msg);
            } catch (Exception $e) {
                // Let's not lose our sleep over that...
            }
        }

        // send the error message in the standard format
        $this->getHttpResponse()->setCode($code);
        $this->sendJson(
            [
                "code" => $code,
                "success" => false,
                "error" => [
                    "message" => $msg,
                    "code" => $frontendErrorCode,
                    "parameters" => $frontendErrorParams
                ]
            ]
        );
    }
}
