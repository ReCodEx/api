<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\BadRequestException;
use App\Model\Repository\GroupExternalAttributes;
use App\Model\Repository\Groups;
use App\Model\Entity\GroupExternalAttribute;
use App\Model\View\GroupViewFactory;
use App\Security\ACL\IGroupPermissions;
use InvalidArgumentException;

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



    /**
     * Return all attributes that correspond to given filtering parameters.
     * @GET
     *
     * The filter is encoded as array of objects (logically represented as disjunction of clauses)
     * -- i.e., [clause1 OR clause2 ...]. Each clause is an object with the following keys:
     * "group", "service", "key", "value" that match properties of GroupExternalAttribute entity.
     * The values are expected values matched with == in the search. Any of the keys may be omitted or null
     * which indicate it should not be matched in the particular clause.
     * A clause must contain at least one of the four keys.
     *
     * The endpoint will return a list of matching attributes and all related group entities.
     */
    #[Query("filter", new VString(), "JSON-encoded filter query in DNF as [clause OR clause...]", required: true)]
    public function actionDefault(?string $filter)
    {
        $this->sendSuccessResponse("OK");
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
        $this->sendSuccessResponse("OK");
    }



    /**
     * Remove selected attribute
     * @DELETE
     */
    #[Path("id", new VUuid(), "Identifier of the external attribute.", required: true)]
    public function actionRemove(string $id)
    {
        $this->sendSuccessResponse("OK");
    }
}
