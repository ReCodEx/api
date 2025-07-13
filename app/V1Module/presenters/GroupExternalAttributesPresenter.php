<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\BadRequestException;
use App\Model\Repository\GroupExternalAttributes;
use App\Model\Repository\Groups;
use App\Model\Entity\GroupExternalAttribute;
use App\Model\View\GroupViewFactory;
use App\Security\ACL\IGroupPermissions;
use DateTime;
use InvalidArgumentException;

/**
 * Additional attributes used by 3rd parties to keep relations between groups and entites in external systems.
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

    public function noncheckDefault()
    {
        if (!$this->groupAcl->canViewExternalAttributes()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Return all attributes that correspond to given filtering parameters.
     * @GET
     * @Param(type="query", name="filter", required=true, validation="string",
     *        description="JSON-encoded filter query in DNF as [clause OR clause...]")
     *
     * The filter is encocded as array of objects (logically represented as disjunction of clauses)
     * -- i.e., [clause1 OR clause2 ...]. Each clause is an object with the following keys:
     * "group", "service", "key", "value" that match properties of GroupExternalAttribute entity.
     * The values are expected values matched with == in the search. Any of the keys may be ommitted or null
     * which indicate it should not be matched in the particular clause.
     * A clause must contain at least one of the four keys.
     *
     * The endpoint will return a list of matching attributes and all related group entities.
     */
    public function actionDefault(?string $filter)
    {
        $this->sendSuccessResponse("OK");
    }


    public function noncheckAdd()
    {
        if (!$this->groupAcl->canSetExternalAttributes()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Create an external attribute for given group.
     * @Param(type="post", name="service", required=true, validation="string:1..32",
     *        description="Identifier of the external service creating the attribute")
     * @Param(type="post", name="key", required=true, validation="string:1..32",
     *        description="Key of the attribute (must be valid identifier)")
     * @Param(type="post", name="value", required=true, validation="string:0..255",
     *        description="Value of the attribute (arbitrary string)")
     * @POST
     */
    public function actionAdd(string $groupId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckRemove()
    {
        if (!$this->groupAcl->canSetExternalAttributes()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Remove selected attribute
     * @DELETE
     */
    public function actionRemove(string $id)
    {
        $this->sendSuccessResponse("OK");
    }
}
