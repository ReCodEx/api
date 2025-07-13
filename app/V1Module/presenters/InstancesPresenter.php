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



    /**
     * Get a list of all instances
     * @GET
     */
    public function actionDefault()
    {
        $this->sendSuccessResponse("OK");
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
        $this->sendSuccessResponse("OK");
    }


    /**
     * Update an instance
     * @POST
     */
    #[Post("isOpen", new VBool(), "Should the instance be open for registration?", required: false)]
    #[Path("id", new VUuid(), "An identifier of the updated instance", required: true)]
    public function actionUpdateInstance(string $id)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Delete an instance
     * @DELETE
     */
    #[Path("id", new VUuid(), "An identifier of the instance to be deleted", required: true)]
    public function actionDeleteInstance(string $id)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Get details of an instance
     * @GET
     */
    #[Path("id", new VUuid(), "An identifier of the instance", required: true)]
    public function actionDetail(string $id)
    {
        $this->sendSuccessResponse("OK");
    }


    /**
     * Get a list of licenses associated with an instance
     * @GET
     */
    #[Path("id", new VUuid(), "An identifier of the instance", required: true)]
    public function actionLicences(string $id)
    {
        $this->sendSuccessResponse("OK");
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
        $this->sendSuccessResponse("OK");
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
        $this->sendSuccessResponse("OK");
    }



    /**
     * Remove existing license for an instance
     * @DELETE
     * @throws NotFoundException
     */
    #[Path("licenceId", new VString(), "Identifier of the license", required: true)]
    public function actionDeleteLicence(string $licenceId)
    {
        $this->sendSuccessResponse("OK");
    }
}
