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
class BrokerPresenter extends BasePresenter
{

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


    public function noncheckStats()
    {
        if (!$this->brokerAcl->canViewStats()) {
            throw new ForbiddenRequestException("You cannot see broker stats");
        }
    }

    /**
     * Get current statistics from broker.
     * @GET
     * @throws InvalidStateException
     * @throws ZMQSocketException
     */
    public function actionStats()
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckFreeze()
    {
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
    public function actionFreeze()
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckUnfreeze()
    {
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
    public function actionUnfreeze()
    {
        $this->sendSuccessResponse("OK");
    }
}
