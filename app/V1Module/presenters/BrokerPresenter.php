<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Security\ACL\IBrokerPermissions;

/**
 * Endpoints for getting status of broker and its management.
 */
class BrokerPresenter extends BasePresenter {

  /**
   * @var IBrokerPermissions
   * @inject
   */
  public $brokerAcl;


  public function checkStatus() {
    if (!$this->brokerAcl->canViewStatus()) {
      throw new ForbiddenRequestException("You cannot see broker status");
    }
  }

  /**
   * Get current status from broker
   * @GET
   */
  public function actionStatus() {
    $this->sendSuccessResponse("OK");
  }

  public function checkFreeze() {
    if (!$this->brokerAcl->canFreeze()) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Freeze broker and its execution.
   * @POST
   */
  public function actionFreeze() {
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
   */
  public function actionUnfreeze() {
    $this->sendSuccessResponse("OK");
  }
}
