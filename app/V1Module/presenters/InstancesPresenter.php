<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Helpers\MetaFormats\Validators\VUuid;
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
     * @throws ForbiddenRequestException
     */
    #[Post("name", new VString(2), "Name of the instance")]
    #[Post("description", new VMixed(), "Description of the instance", required: false, nullable: true)]
    #[Post("isOpen", new VBool(), "Should the instance be open for registration?")]
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
        $this->sendSuccessResponse($this->instanceViewFactory->getInstance($instance, $user), IResponse::S201_Created);
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
     */
    #[Post("isOpen", new VBool(), "Should the instance be open for registration?", required: false)]
    #[Path("id", new VUuid(), "An identifier of the updated instance", required: true)]
    public function actionUpdateInstance(string $id)
    {
        $instance = $this->instances->findOrThrow($id);

        $req = $this->getRequest();
        $isOpen = $req->getPost("isOpen");
        if ($isOpen !== null) {
            $instance->setIsOpen($isOpen);
        }
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
     */
    #[Path("id", new VUuid(), "An identifier of the instance to be deleted", required: true)]
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
     */
    #[Path("id", new VUuid(), "An identifier of the instance", required: true)]
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
     */
    #[Path("id", new VUuid(), "An identifier of the instance", required: true)]
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
     */
    #[Post("note", new VString(2), "A note for users or administrators")]
    #[Post("validUntil", new VTimestamp(), "Expiration date of the license")]
    #[Path("id", new VUuid(), "An identifier of the instance", required: true)]
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
     * @throws NotFoundException
     */
    #[Post("note", new VString(2, 255), "A note for users or administrators", required: false)]
    #[Post("validUntil", new VString(), "Expiration date of the license", required: false)]
    #[Post("isValid", new VBool(), "Administrator switch to toggle license validity", required: false)]
    #[Path("licenceId", new VString(), "Identifier of the license", required: true)]
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
     * @throws NotFoundException
     */
    #[Path("licenceId", new VString(), "Identifier of the license", required: true)]
    public function actionDeleteLicence(string $licenceId)
    {
        $licence = $this->licences->findOrThrow($licenceId);
        $this->licences->remove($licence);
        $this->licences->flush();
        $this->sendSuccessResponse("OK");
    }
}
