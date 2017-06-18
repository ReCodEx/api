<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Model\Repository\HardwareGroups;
use App\Security\ACL\IHardwareGroupPermissions;


/**
 * Hardware groups endpoints
 */
class HardwareGroupsPresenter extends BasePresenter {

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

  /**
   * Get a list of all hardware groups in system
   * @GET
   */
  public function actionDefault() {
    if (!$this->hardwareGroupAcl->canViewAll()) {
      throw new ForbiddenRequestException();
    }

    $hwGroups = $this->hardwareGroups->findAll();
    $this->sendSuccessResponse($hwGroups);
  }

}
