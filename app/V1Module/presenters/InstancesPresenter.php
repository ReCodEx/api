<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\NotFoundException;
use App\Model\Entity\LocalizedGroup;
use App\Model\View\GroupViewFactory;
use App\Model\View\InstanceViewFactory;
use App\Model\View\UserViewFactory;
use App\Security\ACL\IGroupPermissions;
use App\Security\ACL\IInstancePermissions;
use App\Security\ACL\IUserPermissions;
use Nette\Http\IResponse;
use App\Exceptions\BadRequestException;
use App\Model\Repository\Instances;
use App\Model\Repository\Licences;
use App\Model\Entity\Instance;
use App\Model\Entity\Licence;

/**
 * Endpoints for instance manipulation
 */
class InstancesPresenter extends BasePresenter
{
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
     * @var UserViewFactory
     * @inject
     */
    public $userViewFactory;

    /**
     * @var GroupViewFactory
     * @inject
     */
    public $groupViewFactory;

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
     * @var InstanceViewFactory
     * @inject
     */
    public $instanceViewFactory;


    public function noncheckDefault()
    {
        if (!$this->instanceAcl->canViewAll()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get a list of all instances
     * @GET
     */
    public function actionDefault()
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckCreateInstance()
    {
        if (!$this->instanceAcl->canAdd()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Create a new instance
     * @POST
     * @Param(type="post", name="name", validation="string:2..", description="Name of the instance")
     * @Param(type="post", name="description", required=false, description="Description of the instance")
     * @Param(type="post", name="isOpen", validation="bool",
     *        description="Should the instance be open for registration?")
     * @throws ForbiddenRequestException
     */
    public function actionCreateInstance()
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckUpdateInstance(string $id)
    {
        $instance = $this->instances->findOrThrow($id);

        if (!$this->instanceAcl->canUpdate($instance)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Update an instance
     * @POST
     * @Param(type="post", name="isOpen", validation="bool", required=false,
     *        description="Should the instance be open for registration?")
     * @param string $id An identifier of the updated instance
     */
    public function actionUpdateInstance(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDeleteInstance(string $id)
    {
        $instance = $this->instances->findOrThrow($id);
        if (!$this->instanceAcl->canRemove($instance)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Delete an instance
     * @DELETE
     * @param string $id An identifier of the instance to be deleted
     */
    public function actionDeleteInstance(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * @throws BadRequestException if the instance is not allowed
     * @throws ForbiddenRequestException
     */
    public function noncheckDetail(string $id)
    {
        $instance = $this->instances->findOrThrow($id);
        if (!$this->instanceAcl->canViewDetail($instance)) {
            throw new ForbiddenRequestException();
        }

        if (!$instance->isAllowed()) {
            throw new BadRequestException("This instance is not allowed.");
        }
    }

    /**
     * Get details of an instance
     * @GET
     * @param string $id An identifier of the instance
     */
    public function actionDetail(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckLicences(string $id)
    {
        $instance = $this->instances->findOrThrow($id);
        if (!$this->instanceAcl->canViewLicences($instance)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get a list of licenses associated with an instance
     * @GET
     * @param string $id An identifier of the instance
     */
    public function actionLicences(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckCreateLicence(string $id)
    {
        $instance = $this->instances->findOrThrow($id);
        if (!$this->instanceAcl->canAddLicence($instance)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Create a new license for an instance
     * @POST
     * @Param(type="post", name="note", validation="string:2..", description="A note for users or administrators")
     * @Param(type="post", name="validUntil", validation="timestamp", description="Expiration date of the license")
     * @param string $id An identifier of the instance
     */
    public function actionCreateLicence(string $id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckUpdateLicence(string $licenceId)
    {
        $licence = $this->licences->findOrThrow($licenceId);
        if (!$this->instanceAcl->canUpdateLicence($licence)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Update an existing license for an instance
     * @POST
     * @Param(type="post", name="note", validation="string:2..255", required=false,
     *        description="A note for users or administrators")
     * @Param(type="post", name="validUntil", validation="string", required=false,
     *        description="Expiration date of the license")
     * @Param(type="post", name="isValid", validation="bool", required=false,
     *        description="Administrator switch to toggle licence validity")
     * @param string $licenceId Identifier of the licence
     * @throws NotFoundException
     */
    public function actionUpdateLicence(string $licenceId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckDeleteLicence(string $licenceId)
    {
        $licence = $this->licences->findOrThrow($licenceId);
        if (!$this->instanceAcl->canRemoveLicence($licence)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Remove existing license for an instance
     * @DELETE
     * @param string $licenceId Identifier of the licence
     * @throws NotFoundException
     */
    public function actionDeleteLicence(string $licenceId)
    {
        $this->sendSuccessResponse("OK");
    }
}
