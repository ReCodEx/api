<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Model\Repository\RuntimeEnvironments;
use App\Security\ACL\IRuntimeEnvironmentPermissions;

/**
 * Runtime environments endpoints
 */
class RuntimeEnvironmentsPresenter extends BasePresenter
{

    /**
     * @var RuntimeEnvironments
     * @inject
     */
    public $runtimeEnvironments;

    /**
     * @var IRuntimeEnvironmentPermissions
     * @inject
     */
    public $runtimeEnvironmentAcl;

    public function checkDefault()
    {
        if (!$this->runtimeEnvironmentAcl->canViewAll()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get a list of all runtime environments
     * @GET
     */
    public function actionDefault()
    {
        $environments = $this->runtimeEnvironments->findAll();
        $this->sendSuccessResponse($environments);
    }
}
