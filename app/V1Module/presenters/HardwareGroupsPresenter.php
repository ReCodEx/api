<?php

namespace App\V1Module\Presenters;

use App\Model\Repository\HardwareGroups;


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
   * Get a list of all hardware groups in system
   * @GET
   * @UserIsAllowed(hardwareGroups="view-all")
   */
  public function actionDefault() {
    $hwGroups = $this->hardwareGroups->findAll();
    $this->sendSuccessResponse($hwGroups);
  }

}
