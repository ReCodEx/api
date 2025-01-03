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


    public function checkDefault()
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
        $instances = array_filter(
            $this->instances->findAll(),
            function (Instance $instance) {
                return $instance->isAllowed();
            }
        );
        $this->sendSuccessResponse(
            $this->instanceViewFactory->getInstances($instances, $this->getCurrentUserOrNull())
        );
    }

    public function checkCreateInstance()
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
        $req = $this->getRequest();
        $user = $this->getCurrentUser();
        $name = $req->getPost("name");
        $description = $req->getPost("description") ?: "";

        $localizedRootGroup = new LocalizedGroup($this->getCurrentUserLocale(), $name, $description);
        $instance = Instance::createInstance(
            [$localizedRootGroup],
            $req->getPost("isOpen"),
            $user
        );

        $this->instances->persist($instance->getRootGroup(), false);
        $this->instances->persist($localizedRootGroup, false);
        $this->instances->persist($instance);
        $this->sendSuccessResponse($this->instanceViewFactory->getInstance($instance, $user), IResponse::S201_CREATED);
    }

    public function checkUpdateInstance(string $id)
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
        $instance = $this->instances->findOrThrow($id);

        $req = $this->getRequest();
        $isOpen = $req->getPost("isOpen") ? filter_var(
            $req->getPost("isOpen"),
            FILTER_VALIDATE_BOOLEAN
        ) : $instance->isOpen();

        $instance->setIsOpen($isOpen);
        $this->instances->persist($instance);
        $this->sendSuccessResponse($this->instanceViewFactory->getInstance($instance, $this->getCurrentUser()));
    }

    public function checkDeleteInstance(string $id)
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
        $instance = $this->instances->findOrThrow($id);

        $this->instances->remove($instance);
        $this->instances->flush();
        $this->sendSuccessResponse("OK");
    }

    /**
     * @throws BadRequestException if the instance is not allowed
     * @throws ForbiddenRequestException
     */
    public function checkDetail(string $id)
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
        $instance = $this->instances->findOrThrow($id);
        $this->sendSuccessResponse($this->instanceViewFactory->getInstance($instance, $this->getCurrentUser()));
    }

    public function checkLicences(string $id)
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
        $instance = $this->instances->findOrThrow($id);
        $this->sendSuccessResponse($instance->getLicences()->getValues());
    }

    public function checkCreateLicence(string $id)
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
        $instance = $this->instances->findOrThrow($id);
        $req = $this->getRequest();
        $validUntil = (new \DateTime())->setTimestamp($req->getPost("validUntil"));
        $note = $req->getPost("note");

        $licence = Licence::createLicence($note, $validUntil, $instance);
        $this->licences->persist($licence);
        $this->sendSuccessResponse($licence);
    }

    public function checkUpdateLicence(string $licenceId)
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
        $licence = $this->licences->findOrThrow($licenceId);
        $req = $this->getRequest();
        $validUntil = $req->getPost("validUntil") ? new \DateTime(
            $req->getPost("validUntil")
        ) : $licence->getValidUntil();
        $isValid = $req->getPost("isValid") ? filter_var(
            $req->getPost("isValid"),
            FILTER_VALIDATE_BOOLEAN
        ) : $licence->isValid();

        $licence->setNote($req->getPost("note") ?: $licence->getNote());
        $licence->setValidUntil($validUntil);
        $licence->setIsValid($isValid);

        $this->licences->persist($licence);
        $this->sendSuccessResponse($licence);
    }

    public function checkDeleteLicence(string $licenceId)
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
        $licence = $this->licences->findOrThrow($licenceId);
        $this->licences->remove($licence);
        $this->licences->flush();
        $this->sendSuccessResponse("OK");
    }
}
