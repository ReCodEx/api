<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Model\Repository\RuntimeEnvironments;
use App\Security\ACL\IRuntimeEnvironmentPermissions;


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
   * @var IRuntimeEnvironmentPermissions
   * @inject
   */
  public $runtimeEnvironmentAcl;

  /**
   * Get a list of all runtime environments
   * @GET
   * @throws ForbiddenRequestException
   */
  public function actionDefault() {
    if (!$this->runtimeEnvironmentAcl->canViewAll()) {
      throw new ForbiddenRequestException();
    }

    $environments = $this->runtimeEnvironments->findAll();
    $this->sendSuccessResponse($environments);
  }

}
