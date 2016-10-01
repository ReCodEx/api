<?php

namespace App\V1Module\Presenters;

use Nette\Http\IResponse;

use App\Exceptions\NotFoundException;
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
   * @LoggedIn
   * @UserIsAllowed(instances="add")
   * @Param(type="post", name="name", validation="string:2..")
   * @Param(type="post", name="description", required=FALSE)
   * @Param(type="post", name="isOpen", validation="bool")
   */
  public function actionCreateInstance() {
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
   * @LoggedIn
   * @UserIsAllowed(instances="update")
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
   * @LoggedIn
   * @UserIsAllowed(instances="remove")
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
   * @LoggedIn
   * @UserIsAllowed(instances="view-groups")
   */
  public function actionGroups(string $id) {
    $instance = $this->findInstanceOrThrow($id);
    $this->sendSuccessResponse($instance->getGroups()->toArray());
  }

  /**
   * @GET
   * @LoggedIn
   * @UserIsAllowed(instances="view-users")
   */
  public function actionUsers(string $id, string $search = NULL) {
    $instance = $this->findInstanceOrThrow($id);
    $user = $this->findUserOrThrow("me");
    if (!$user->belongsTo($instance)
      || !$this->user->isInRole("superadmin")) { // @todo: use privilidges instead of roles
        throw new ForbiddenRequestException("You cannot access this instance users."); 
    }

    $members = $instance->getMembers($search);
    $this->sendSuccessResponse($members->toArray());
  }

  /**
   * @GET
   * @LoggedIn
   * @UserIsAllowed(instances="view-licences")
   */
  public function actionLicences(string $id) {
    $instance = $this->findInstanceOrThrow($id);
    $this->sendSuccessResponse($instance->getLicences()->toArray());
  }

  /**
   * @POST
   * @LoggedIn
   * @UserIsAllowed(instances="add-licence")
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
