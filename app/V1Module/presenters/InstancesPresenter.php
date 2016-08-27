<?php

namespace App\V1Module\Presenters;

use App\Exception\NotFoundException;
use App\Model\Repository\Instances;

class InstancesPresenter extends BasePresenter {

  /** @inject @var Instances */
  public $instances;

  protected function findInstanceOrThrow(string $id) {
    $instance = $this->instances->get($id);
    if (!$instance) {
      throw new NotFoundException("Instance $id");
    }

    return $instance;
  }

  /**
   * @GET
   */
  public function actionDefault() {
    $instances = $this->instances->findAll();
    $this->sendSuccessResponse($instances);
  }

  /**
   * @GET
   */
  public function actionDetail(string $id) {
    $instance = $this->findInstanceOrThrow($id);
    $this->sendSuccessResponse($instance);
  }

  /**
   * @GET
   */
  public function actionGroups(string $id) {
    $instance = $this->findInstanceOrThrow($id);
    $this->sendSuccessResponse($instance->getGroups()->toArray());
  }

}
