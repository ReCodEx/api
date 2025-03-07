<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Type;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VDouble;
use App\Helpers\MetaFormats\Validators\VEmail;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Helpers\MetaFormats\Validators\VUuid;
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
