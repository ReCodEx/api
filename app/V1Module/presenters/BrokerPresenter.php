<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Type;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VEmail;
use App\Helpers\MetaFormats\Validators\VFloat;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Helpers\MetaFormats\Validators\VUuid;
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


    public function checkStats()
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
        $stats = $this->brokerProxy->getStats();
        $this->sendSuccessResponse($stats);
    }

    public function checkFreeze()
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
        $this->brokerProxy->freeze();
        $this->sendSuccessResponse("OK");
    }

    public function checkUnfreeze()
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
        $this->brokerProxy->unfreeze();
        $this->sendSuccessResponse("OK");
    }
}
