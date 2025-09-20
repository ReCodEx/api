<?php

namespace App\V1Module\Presenters;

use App\Exceptions\BadRequestException;
use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\NotFoundException;
use App\Exceptions\InternalServerException;
use App\Model\Repository\GroupExternalAttributes;
use App\Model\Repository\GroupMemberships;
use App\Model\Repository\Groups;
use App\Model\Entity\GroupExternalAttribute;
use App\Model\View\GroupViewFactory;
use App\Security\ACL\IGroupPermissions;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

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

    public function checkGet(string $groupId)
    {
        $group = $this->groups->findOrThrow($groupId);
        if (!$this->groupAcl->canViewDetail($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Get all external attributes for a group. This endpoint is meant to be used by webapp to display the attributes.
     * @GET
     */
    #[Path("groupId", new VUuid(), required: true)]
    public function actionGet(string $groupId)
    {
        $attributes = $this->groupExternalAttributes->findBy(['group' => $groupId]);
        $this->sendSuccessResponse($attributes);
    }

    public function checkAdd(string $groupId)
    {
        $group = $this->groups->findOrThrow($groupId);
        if (!$this->groupAcl->canSetExternalAttributes($group)) {
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
    #[Path("groupId", new VUuid(), required: true)]
    public function actionAdd(string $groupId)
    {
        $group = $this->groups->findOrThrow($groupId);

        $req = $this->getRequest();
        $service = $req->getPost("service");
        $key = $req->getPost("key");
        $value = $req->getPost("value");

        try {
            $attribute = new GroupExternalAttribute($group, $service, $key, $value);
            $this->groupExternalAttributes->persist($attribute);
        } catch (UniqueConstraintViolationException) {
            throw new BadRequestException("Attribute already exists.");
        }

        $this->sendSuccessResponse("OK");
    }

    public function checkRemove(string $groupId)
    {
        $group = $this->groups->findOrThrow($groupId);
        if (!$this->groupAcl->canSetExternalAttributes($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Remove selected attribute
     * @DELETE
     */
    #[Query("service", new VString(1, 32), "Identifier of the external service creating the attribute", required: true)]
    #[Query("key", new VString(1, 32), "Key of the attribute (must be valid identifier)", required: true)]
    #[Query("value", new VString(0, 255), "Value of the attribute (arbitrary string)", required: true)]
    #[Path("groupId", new VUuid(), required: true)]
    public function actionRemove(string $groupId, string $service, string $key, string $value)
    {
        $attributes = $this->groupExternalAttributes->findBy(
            ['group' => $groupId, 'service' => $service, 'key' => $key, 'value' => $value]
        );
        if (!$attributes) {
            throw new NotFoundException("Specified attribute not found at selected group");
        }
        if (count($attributes) > 1) {
            throw new InternalServerException(
                "Unique constraint violation "
                    . "(multiple '$key' => '$value' attributes found at $groupId from service $service)"
            );
        }

        $this->groupExternalAttributes->remove($attributes[0]);
        $this->sendSuccessResponse("OK");
    }
}
