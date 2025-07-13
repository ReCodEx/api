<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Helpers\MetaFormats\Validators\VUuid;
use App\Exceptions\ForbiddenRequestException;
use App\Model\Repository\GroupInvitations;
use App\Model\Repository\Groups;
use App\Model\Entity\GroupInvitation;
use App\Model\View\GroupViewFactory;
use App\Security\ACL\IGroupPermissions;
use DateTime;

/**
 * Group invitations - links that allow users to join a group.
 */
class GroupInvitationsPresenter extends BasePresenter
{
    /**
     * @var GroupInvitations
     * @inject
     */
    public $groupInvitations;

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
     * Return invitation details including all relevant group entities (so a name can be constructed).
     * @GET
     */
    #[Path("id", new VUuid(), "Identifier of the group invitation", required: true)]
    public function actionDefault($id)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Edit the invitation.
     * @POST
     */
    #[Post("expireAt", new VTimestamp(), "When the invitation expires.", nullable: true)]
    #[Post("note", new VMixed(), "Note for the students who wish to use the invitation link.", nullable: true)]
    #[Path("id", new VUuid(), "Identifier of the group invitation", required: true)]
    public function actionUpdate($id)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * @DELETE
     */
    #[Path("id", new VUuid(), "Identifier of the group invitation", required: true)]
    public function actionRemove($id)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Allow the current user to join the corresponding group using the invitation.
     * @POST
     */
    #[Path("id", new VUuid(), "Identifier of the group invitation", required: true)]
    public function actionAccept($id)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * List all invitations of a group.
     * @GET
     */
    #[Path("groupId", new VString(), required: true)]
    public function actionList($groupId)
    {
        $this->sendSuccessResponse("OK");
    }



    /**
     * Create a new invitation for given group.
     * @POST
     */
    #[Post("expireAt", new VTimestamp(), "When the invitation expires.", nullable: true)]
    #[Post("note", new VMixed(), "Note for the students who wish to use the invitation link.", nullable: true)]
    #[Path("groupId", new VString(), required: true)]
    public function actionCreate($groupId)
    {
        $this->sendSuccessResponse("OK");
    }
}
