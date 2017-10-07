<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Model\Entity\Group;
use App\Model\Entity\User;
use App\Security\ACL\IGroupPermissions;
use App\Security\ACL\IInstancePermissions;
use App\Security\ACL\IUserPermissions;
use App\Security\Identity;
use Nette\Http\IResponse;

use App\Exceptions\BadRequestException;
use App\Model\Repository\Instances;
use App\Model\Repository\Licences;
use App\Model\Entity\Instance;
use App\Model\Entity\Licence;

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
   * @var IInstancePermissions
   * @inject
   */
  public $instanceAcl;

  /**
   * @var IGroupPermissions
   * @inject
   */
  public $groupAcl;

  /**
   * @var IUserPermissions
   * @inject
   */
  public $userAcl;

  /**
   * Get a list of all instances
   * @GET
   */
  public function actionDefault() {
    if (!$this->instanceAcl->canViewAll()) {
      throw new ForbiddenRequestException();
    }

    $instances = array_filter($this->instances->findAll(),
        function (Instance $instance) { return $instance->isAllowed(); }
    );
    /** @var Identity $identity */
    $identity = $this->getUser()->getIdentity();
    $user = $identity ? $identity->getUserData() : NULL;
    $instancesData = array_map(function (Instance $instance) use ($user) {
      return $instance->getData($user);
    }, $instances);
    $this->sendSuccessResponse(array_values($instancesData));
  }

  /**
   * Create a new instance
   * @POST
   * @Param(type="post", name="name", validation="string:2..", description="Name of the instance")
   * @Param(type="post", name="description", required=FALSE, description="Description of the instance")
   * @Param(type="post", name="isOpen", validation="bool", description="Should the instance be open for registration?")
   */
  public function actionCreateInstance() {
    if (!$this->instanceAcl->canAdd()) {
      throw new ForbiddenRequestException();
    }

    $params = $this->parameters;
    $user = $this->getCurrentUser();
    $instance = Instance::createInstance(
      $params->name,
      $params->isOpen,
      $user,
      $params->description
    );
    $this->instances->persist($instance);
    $this->sendSuccessResponse($instance->getData($this->getCurrentUser()), IResponse::S201_CREATED);
  }

  /**
   * Update an instance
   * @POST
   * @Param(type="post", name="name", validation="string:2..", required=FALSE, description="Name of the instance")
   * @Param(type="post", name="description", required=FALSE, description="Description of the instance")
   * @Param(type="post", name="isOpen", validation="bool", required=FALSE, description="Should the instance be open for registration?")
   * @param string $id An identifier of the updated instance
   * @throws ForbiddenRequestException
   */
  public function actionUpdateInstance(string $id) {
    $instance = $this->instances->findOrThrow($id);

    if (!$this->instanceAcl->canUpdate($instance)) {
      throw new ForbiddenRequestException();
    }

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
    $this->sendSuccessResponse($instance->getData($this->getCurrentUser()));
  }

  /**
   * Delete an instance
   * @DELETE
   * @param string $id An identifier of the instance to be deleted
   * @throws ForbiddenRequestException
   */
  public function actionDeleteInstance(string $id) {
    $instance = $this->instances->findOrThrow($id);
    if (!$this->instanceAcl->canRemove($instance)) {
      throw new ForbiddenRequestException();
    }

    $this->instances->remove($instance);
    $this->instances->flush();
    $this->sendSuccessResponse("OK");
  }

  /**
   * Get details of an instance
   * @GET
   * @param string $id An identifier of the instance
   * @throws BadRequestException if the instance is not allowed
   * @throws ForbiddenRequestException
   */
  public function actionDetail(string $id) {
    $instance = $this->instances->findOrThrow($id);
    if (!$this->instanceAcl->canViewDetail($instance)) {
      throw new ForbiddenRequestException();
    }

    if (!$instance->getIsAllowed()) {
      throw new BadRequestException("This instance is not allowed.");
    }
    $user = $this->getCurrentUser();
    $this->sendSuccessResponse($instance->getData($user));
  }

  /**
   * Get a list of all groups which user can view in an instance
   * @GET
   * @param string $id An identifier of the instance
   * @throws ForbiddenRequestException
   */
  public function actionGroups(string $id) {
    $instance = $this->instances->findOrThrow($id);
    if (!$this->instanceAcl->canViewGroups($instance)) {
      throw new ForbiddenRequestException();
    }

    $this->sendSuccessResponse(array_values(array_filter($instance->getGroups()->getValues(), function (Group $group) {
      return $this->groupAcl->canViewDetail($group);
    })));
  }

  /**
   * Get a list of all public groups in an instance.
   * @GET
   * @param string $id An identifier of the instance
   * @throws ForbiddenRequestException
   */
  public function actionPublicGroups(string $id) {
    $instance = $this->instances->findOrThrow($id);
    if (!$this->instanceAcl->canViewGroups($instance)) {
      throw new ForbiddenRequestException();
    }

    $groups = array_filter($instance->getGroups()->getValues(), function (Group $group) {
      return $this->groupAcl->canViewPublicDetail($group);
    });
    $publicGroups = array_map(function (Group $group) {
      return $group->getPublicData($this->groupAcl->canViewDetail($group));
    }, $groups);
    $this->sendSuccessResponse(array_values($publicGroups));
  }

  /**
   * Get a list of users registered in an instance
   * @GET
   * @param string $id An identifier of the instance
   * @param string $search A result filter
   * @throws ForbiddenRequestException
   */
  public function actionUsers(string $id, string $search = NULL) {
    $instance = $this->instances->findOrThrow($id);
    if (!$this->instanceAcl->canViewUsers($instance)) {
      throw new ForbiddenRequestException();
    }

    $members = $instance->getMembers($search);
    $members = array_filter($members, function (User $user) {
      return $this->userAcl->canViewPublicData($user);
    });
    $members = array_map(function (User $user) {
      return $user->getPublicData();
    }, $members);
    $this->sendSuccessResponse(array_values($members));
  }

  /**
   * Get a list of licenses associated with an instance
   * @GET
   * @param string $id An identifier of the instance
   * @throws ForbiddenRequestException
   */
  public function actionLicences(string $id) {
    $instance = $this->instances->findOrThrow($id);
    if (!$this->instanceAcl->canViewLicences($instance)) {
      throw new ForbiddenRequestException();
    }

    $this->sendSuccessResponse($instance->getLicences()->getValues());
  }

  /**
   * Create a new license for an instance
   * @POST
   * @Param(type="post", name="note", validation="string:2..", description="A note for users or administrators")
   * @Param(type="post", name="validUntil", validation="numericint", description="Expiration date of the license")
   * @param string $id An identifier of the instance
   * @throws ForbiddenRequestException
   */
  public function actionCreateLicence(string $id) {
    $params = $this->parameters;
    $instance = $this->instances->findOrThrow($id);

    if (!$this->instanceAcl->canAddLicence($instance)) {
      throw new ForbiddenRequestException();
    }

    $validUntil = (new \DateTime())->setTimestamp($params->validUntil);
    $licence = Licence::createLicence($params->note, $validUntil, $instance);
    $this->licences->persist($licence);
    $this->sendSuccessResponse($licence);
  }

  /**
   * Update an existing license for an instance
   * @POST
   * @Param(type="post", name="note", validation="string:2..", required=FALSE, description="A note for users or administrators")
   * @Param(type="post", name="validUntil", validation="string", required=FALSE, description="Expiration date of the license")
   * @Param(type="post", name="isValid", validation="bool", required=FALSE, description="Administrator switch to toggle licence validity")
   * @param string $licenceId Identifier of the licence
   * @throws ForbiddenRequestException
   */
  public function actionUpdateLicence(string $licenceId) {
    $params = $this->parameters;
    $licence = $this->licences->findOrThrow($licenceId);

    if (!$this->instanceAcl->canUpdateLicence($licence)) {
      throw new ForbiddenRequestException();
    }

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
   * @param string $licenceId Identifier of the licence
   * @throws ForbiddenRequestException
   */
  public function actionDeleteLicence(string $licenceId) {
    $licence = $this->licences->findOrThrow($licenceId);
    if (!$this->instanceAcl->canRemoveLicence($licence)) {
      throw new ForbiddenRequestException();
    }
    $this->licences->remove($licence);
    $this->licences->flush();
    $this->sendSuccessResponse("OK");
  }

}
