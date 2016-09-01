<?php

namespace App\V1Module\Presenters;

use Nette\Http\IResponse;

use App\Exception\NotFoundException;
use App\Model\Repository\Instances;
use App\Model\Repository\Licences;
use App\Model\Entity\Instance;
use App\Model\Entity\Licence;

class InstancesPresenter extends BasePresenter {

  /** @inject @var Instances */
  public $instances;

  /** @inject @var Licences */
  public $licences;

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
   * @POST
   * @Param(type="post", name="name", validation="string:2..")
   * @Param(type="post", name="description", required=FALSE)
   * @Param(type="post", name="isOpen", validation="bool")
   */
  public function actionAddInstance() {
    $params = $this->parameters;
    $user = $this->findUserOrThrow('me');
    $instance = Instance::createInstance(
      $params->name,
      $params->isOpen,
      $user,
      $params->description
    );
    $this->instances->persist($instance);
    $this->sendSuccessResponse($instance, IResponse::S201_CREATED);
  }

  /**
   * @PUT
   * @Param(type="post", name="name", validation="string:2..")
   * @Param(type="post", name="description", required=FALSE)
   * @Param(type="post", name="isOpen", validation="bool", required=FALSE)
   */
  public function actionUpdateInstance(string $id) {
    $instance = $this->findInstanceOrThrow($id);
    $params = $this->parameters;
    if (isset($params->name)) {
      $instance->name = $params->name;
    }
    if (isset($params->description)) {
      $instance->description = $params->description;
    }
    if (isset($params->isOpen)) {
      $instance->isOpen = $params->isOpen;
    }
    $this->instances->persist($instance);
    $this->sendSuccessResponse($instance);
  }

  /**
   * @DELETE
   */
  public function actionDeleteInstance(string $id) {
    $instance = $this->findInstanceOrThrow($id);
    $this->instances->remove($instance);
    $this->sendSuccessResponse([]);
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

  /**
   * @GET
   */
  public function actionLicences(string $id) {
    $instance = $this->findInstanceOrThrow($id);
    $this->sendSuccessResponse($instance->getLicences()->toArray());
  }

  /**
   * @POST
   * @Param(type="post", name="note", validation="string:2..")
   * @Param(type="post", name="validUntil", validation="datetime")
   */
  public function actionCreateLicence(string $id) {
    $params = $this->parameters;
    $instance = $this->findInstanceOrThrow($id);
    $licence = Licence::createLicence($params->note, $params->validUntil, $instance);
    $this->licences->persist($licence);
    $this->sendSuccessResponse($licence);
  }

}
