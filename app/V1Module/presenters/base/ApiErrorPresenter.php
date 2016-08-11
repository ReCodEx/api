<?php

namespace App\V1Module\Presenters;

use App\Exception\ApiException;
use Nette\Application;
use Nette\Http\IResponse;
use Nette\Application\BadRequestException;
use Doctrine\DBAL\Exception\ConnectionException;
use Tracy\Debugger;

/**
 * The error presenter for the API module - all responses are served as JSONs with a fixed format.
 */
class ApiErrorPresenter extends \App\Presenters\BasePresenter {

  /**
   * @param  Exception
   * @return void
   */
  public function renderDefault($exception) {
    if ($exception instanceof ApiException) {
      $this->handleAPIException($exception);
    } elseif ($exception instanceof BadRequestException) {
      $this->sendErrorResponse($exception->getCode(), "Bad Request");
    } elseif ($exception instanceof ConnectionException) {
      $this->sendErrorResponse(IResponse::S500_INTERNAL_SERVER_ERROR, "Database is offline");
    } else {
      $type = get_class($exception);
      Debugger::log($exception);
      $this->sendErrorResponse(IResponse::S500_INTERNAL_SERVER_ERROR, "Unexpected Error {$type}");
    }
  }

  /**
    * Send an error response based on a known type of exceptions - derived from ApiException
    * @param  ApiException $exception The exception which caused the error
    */
  protected function handleAPIException(ApiException $exception) {
    $this->sendErrorResponse($exception->getCode(), $exception->getMessage());
  }

  /**
    * Send a JSON response with a specific HTTP code
    * @param  int      $code HTTP code of the response
    * @param  string   $msg  Human readable description of the error
    * @return void
    */
  protected function sendErrorResponse($code, $msg) {
    $this->getHttpResponse()->setCode($code);
    $this->sendJson([
        "code"      => $code,
        "success"   => FALSE,
        "msg"       => $msg
    ]);
  }

  /**
    * Log the error respose
    * @param  Nette\Application\IResponse $response The response information
    * @return void
    */
  protected function shutdown($response) {
    // @todo log the error
  }

}
