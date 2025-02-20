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
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Exceptions\ForbiddenRequestException;
use App\Model\Repository\HardwareGroups;
use App\Security\ACL\IHardwareGroupPermissions;

/**
 * Hardware groups endpoints
 */
class HardwareGroupsPresenter extends BasePresenter
{

    /**
     * @var HardwareGroups
     * @inject
     */
    public $hardwareGroups;

    /**
     * @var IHardwareGroupPermissions
     * @inject
     */
    public $hardwareGroupAcl;

    public function checkDefault()
    {
        if (!$this->hardwareGroupAcl->canViewAll()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get a list of all hardware groups in system
     * @GET
     */
    public function actionDefault()
    {
        $hwGroups = $this->hardwareGroups->findAll();
        $this->sendSuccessResponse($hwGroups);
    }
}
