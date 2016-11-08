<?php

namespace App\V1Module\Presenters;

use Nette\Http\IResponse;

use App\Exceptions\NotFoundException;
use App\Model\Repository\Instances;
use App\Model\Repository\Licences;
use App\Model\Entity\Instance;
use App\Model\Entity\Licence;
use Doctrine\Common\Collections\Criteria;

/**
 * Endpoints for instance manipulation
 */
class InstancesPresenter extends BasePresenter {

  /**
   * @var Instances
   * @inject
   */
  public $instances;

  /**
   * @var Licences
   * @inject
   */
  public $licences;

  /**
   * Get a list of all instances
   * @GET
   */
  public function actionDefault() {
    $instances = $this->instances->findAll(); // @todo: Filter out the non-public
    $this->sendSuccessResponse($instances);
  }

  /**
   * Create a new instance
   * @POST
   * @LoggedIn
   * @UserIsAllowed(instances="add")
   * @Param(type="post", name="name", validation="string:2..", description="Name of the instance")
   * @Param(type="post", name="description", required=FALSE, description="Description of the instance")
   * @Param(type="post", name="isOpen", validation="bool", description="Should the instance be open for registration?")
   */
  public function actionCreateInstance() {
    $params = $this->parameters;
    $user = $this->users->findCurrentUserOrThrow();
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
   * Update an instance
   * @POST
   * @LoggedIn
   * @UserIsAllowed(instances="update")
   * @Param(type="post", name="name", validation="string:2..", required=FALSE, description="Name of the instance")
   * @Param(type="post", name="description", required=FALSE, description="Description of the instance")
   * @Param(type="post", name="isOpen", validation="bool", required=FALSE, description="Should the instance be open for registration?")
   */
  public function actionUpdateInstance(string $id) {
    $instance = $this->instances->findOrThrow($id);
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
   * Delete an instance
   * @DELETE
   * @LoggedIn
   * @UserIsAllowed(instances="remove")
   */
  public function actionDeleteInstance(string $id) {
    $instance = $this->instances->findOrThrow($id);
    $this->instances->remove($instance);
    $this->instances->flush();
    $this->sendSuccessResponse("OK");
  }

  /**
   * Get details of an instance
   * @GET
   */
  public function actionDetail(string $id) {
    $instance = $this->instances->findOrThrow($id);
    $this->sendSuccessResponse($instance);
  }

  /**
   * Get a list of groups in an instance
   * @GET
   * @LoggedIn
   * @UserIsAllowed(instances="view-groups")
   */
  public function actionGroups(string $id) {
    $user = $this->users->findCurrentUserOrThrow();
    $instance = $this->instances->findOrThrow($id);
    $this->sendSuccessResponse($instance->getGroupsForUser($user));
  }

  /**
   * Get a list of users registered in an instance
   * @GET
   * @LoggedIn
   * @UserIsAllowed(instances="view-users")
   */
  public function actionUsers(string $id, string $search = NULL) {
    $instance = $this->instances->findOrThrow($id);
    $user = $this->users->findCurrentUserOrThrow();
    if (!$user->belongsTo($instance)
      && $user->getRole()->hasLimitedRights()) {
        throw new ForbiddenRequestException("You cannot access this instance users.");
    }

    $members = $instance->getMembers($search);
    $this->sendSuccessResponse($members->getValues());
  }

  /**
   * Get a list of licenses associated with an instance
   * @GET
   * @LoggedIn
   * @UserIsAllowed(instances="view-licences")
   */
  public function actionLicences(string $id) {
    $instance = $this->instances->findOrThrow($id);
    $this->sendSuccessResponse($instance->getLicences()->getValues());
  }

  /**
   * Create a new license for an instance
   * @POST
   * @LoggedIn
   * @UserIsAllowed(instances="add-licence")
   * @Param(type="post", name="note", validation="string:2..", description="A note for users or administrators")
   * @Param(type="post", name="validUntil", validation="string", description="Expiration date of the license")
   */
  public function actionCreateLicence(string $id) {
    $params = $this->parameters;
    $instance = $this->instances->findOrThrow($id);
    $validUntil = new \DateTime($params->validUntil);
    $licence = Licence::createLicence($params->note, $validUntil, $instance);
    $this->licences->persist($licence);
    $this->sendSuccessResponse($licence);
  }

  /**
   * Update existing license for an instance
   * @POST
   * @LoggedIn
   * @UserIsAllowed(instances="update-licence")
   * @Param(type="post", name="note", validation="string:2..", required=FALSE, description="A note for users or administrators")
   * @Param(type="post", name="validUntil", validation="string", required=FALSE, description="Expiration date of the license")
   * @Param(type="post", name="isValid", validation="bool", required=FALSE, description="Administrator switch to toggle licence validity")
   */
  public function actionUpdateLicence(string $licenceId) {
    $params = $this->parameters;
    $licence = $this->licences->findOrThrow($licenceId);

    if (isset($params->note)) {
      $licence->note = $params->note;
    }
    if (isset($params->validUntil)) {
      $licence->validUntil = new \DateTime($params->validUntil);
    }
    if (isset($params->isValid)) {
      $licence->isValid = filter_var($params->isValid, FILTER_VALIDATE_BOOLEAN);
    }

    $this->licences->persist($licence);
    $this->sendSuccessResponse($licence);
  }

  /**
   * Remove existing license for an instance
   * @DELETE
   * @LoggedIn
   * @UserIsAllowed(instances="remove-licence")
   */
  public function actionDeleteLicence(string $licenceId) {
    $licence = $this->licences->findOrThrow($licenceId);
    $this->licences->remove($licence);
    $this->licences->flush();
    $this->sendSuccessResponse("OK");
  }

}
