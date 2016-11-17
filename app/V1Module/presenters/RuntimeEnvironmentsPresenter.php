<?php

namespace App\V1Module\Presenters;

use App\Model\Repository\RuntimeEnvironments;


/**
 * Runtime environments endpoints
 */
class RuntimeEnvironmentsPresenter extends BasePresenter {

  /**
   * @var RuntimeEnvironments
   * @inject
   */
  public $runtimeEnvironments;

  /**
   * Get a list of all runtime environments
   * @GET
   * @UserIsAllowed(runtimeEnvironments="view-all")
   */
  public function actionDefault() {
    $environments = $this->runtimeEnvironments->findAll();
    $this->sendSuccessResponse($environments);
  }

}
