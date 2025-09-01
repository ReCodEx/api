<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Exceptions\ForbiddenRequestException;
use App\Model\Repository\GroupExternalAttributes;
use App\Model\Repository\GroupMemberships;
use App\Model\Repository\Groups;
use App\Model\Entity\GroupExternalAttribute;
use App\Model\View\GroupViewFactory;
use App\Security\ACL\IGroupPermissions;

/**
 * Additional attributes used by 3rd parties to keep relations between groups and entities in external systems.
 * In case of a university, the attributes may hold things like course/semester/student-group identifiers.
 */
class GroupExternalAttributesPresenter extends BasePresenter
{
    /**
     * @var GroupExternalAttributes
     * @inject
     */
    public $groupExternalAttributes;

    /**
     * @var GroupMemberships
     * @inject
     */
    public $groupMemberships;

    /**
     * @var Groups
     * @inject
     */
    public $groups;

    /**
     * @var IGroupPermissions
     * @inject
     */
    public $groupAcl;

    /**
     * @var GroupViewFactory
     * @inject
     */
    public $groupViewFactory;

    public function checkDefault()
    {
        if (!$this->groupAcl->canViewExternalAttributes()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Return special brief groups entities with injected external attributes and given user affiliation.
     * @GET
     */
    #[Query("instance", new VUuid(), "ID of the instance, whose groups are returned.", required: true)]
    #[Query(
        "service",
        new VString(),
        "ID of the external service, of which the attributes are returned. If missing, all attributes are returned.",
        required: false
    )]
    #[Query(
        "user",
        new VUuid(),
        "Relationship info of this user is included for each returned group.",
        required: false
    )]
    public function actionDefault(string $instance, ?string $service, ?string $user)
    {
        $filter = $service ? [['service' => $service]] : [];
        $attributes = $this->groupExternalAttributes->findByFilter($filter); // all attributes of selected service
        $groups = $this->groups->findFiltered(null, $instance, null, false); // all but archived groups
        $memberships = $user ? $this->groupMemberships->findByUser($user) : [];

        $this->sendSuccessResponse($this->groupViewFactory->getGroupsForExtension(
            $groups,
            $attributes,
            $memberships,
        ));
    }


    public function checkAdd()
    {
        if (!$this->groupAcl->canSetExternalAttributes()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Create an external attribute for given group.
     * @POST
     */
    #[Post("service", new VString(1, 32), "Identifier of the external service creating the attribute", required: true)]
    #[Post("key", new VString(1, 32), "Key of the attribute (must be valid identifier)", required: true)]
    #[Post("value", new VString(0, 255), "Value of the attribute (arbitrary string)", required: true)]
    #[Path("groupId", new VString(), required: true)]
    public function actionAdd(string $groupId)
    {
        $group = $this->groups->findOrThrow($groupId);

        $req = $this->getRequest();
        $service = $req->getPost("service");
        $key = $req->getPost("key");
        $value = $req->getPost("value");
        $attribute = new GroupExternalAttribute($group, $service, $key, $value);
        $this->groupExternalAttributes->persist($attribute);

        $this->sendSuccessResponse("OK");
    }

    public function checkRemove()
    {
        if (!$this->groupAcl->canSetExternalAttributes()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Remove selected attribute
     * @DELETE
     */
    #[Path("id", new VUuid(), "Identifier of the external attribute.", required: true)]
    public function actionRemove(string $id)
    {
        $attribute = $this->groupExternalAttributes->findOrThrow($id);
        $this->groupExternalAttributes->remove($attribute);
        $this->sendSuccessResponse("OK");
    }
}
