<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidStateException;
use App\Helpers\BrokerProxy;
use App\Security\ACL\IBrokerPermissions;
use ZMQSocketException;

/**
 * Endpoints for getting status of broker and its management.
 */
class BrokerPresenter extends BasePresenter {

  /**
   * @var IBrokerPermissions
   * @inject
   */
  public $brokerAcl;

  /**
   * @var BrokerProxy
   * @inject
   */
  public $brokerProxy;


  public function checkStatus() {
    if (!$this->brokerAcl->canViewStatus()) {
      throw new ForbiddenRequestException("You cannot see broker status");
    }
  }

  /**
   * Get current status from broker
   * @GET
   * @throws InvalidStateException
   * @throws ZMQSocketException
   */
  public function actionStatus() {
    $status = $this->brokerProxy->getStatus();
    $this->sendSuccessResponse($status);
  }

  public function checkFreeze() {
    if (!$this->brokerAcl->canFreeze()) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Freeze broker and its execution.
   * @POST
   * @throws InvalidStateException
   * @throws ZMQSocketException
   */
  public function actionFreeze() {
    $this->brokerProxy->freeze();
    $this->sendSuccessResponse("OK");
  }

  public function checkUnfreeze() {
    if (!$this->brokerAcl->canUnfreeze()) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Unfreeze broker and its execution.
   * @POST
   * @throws InvalidStateException
   * @throws ZMQSocketException
   */
  public function actionUnfreeze() {
    $this->brokerProxy->unfreeze();
    $this->sendSuccessResponse("OK");
  }
}
