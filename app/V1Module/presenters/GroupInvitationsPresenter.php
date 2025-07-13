<?php

namespace App\V1Module\Presenters;

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

    public function noncheckDefault($id)
    {
        $invitation = $this->groupInvitations->findOrThrow($id);
        if (!$invitation->getGroup() || !$this->groupAcl->canViewInvitations($invitation->getGroup())) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Return invitation details including all relevant group entities (so a name can be constructed).
     * @GET
     */
    public function actionDefault($id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckUpdate($id)
    {
        $invitation = $this->groupInvitations->findOrThrow($id);
        if (!$invitation->getGroup() || !$this->groupAcl->canEditInvitations($invitation->getGroup())) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Edit the invitation.
     * @POST
     * @Param(name="expireAt", type="post", validation="timestamp|null", description="When the invitation expires.")
     * @Param(name="note", type="post", description="Note for the students who wish to use the invitation link.")
     */
    public function actionUpdate($id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckRemove($id)
    {
        $invitation = $this->groupInvitations->findOrThrow($id);
        if (!$invitation->getGroup() || !$this->groupAcl->canEditInvitations($invitation->getGroup())) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * @DELETE
     */
    public function actionRemove($id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckAccept($id)
    {
        $invitation = $this->groupInvitations->findOrThrow($id);
        if (
            $invitation->hasExpired()
            || !$invitation->getGroup()
            || !$this->groupAcl->canAcceptInvitation($invitation->getGroup())
            || $invitation->getGroup()->isOrganizational()
        ) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Allow the current user to join the corresponding group using the invitation.
     * @POST
     */
    public function actionAccept($id)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckList($groupId)
    {
        $group = $this->groups->findOrThrow($groupId);
        if (!$this->groupAcl->canViewDetail($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * List all invitations of a group.
     * @GET
     */
    public function actionList($groupId)
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckCreate($groupId)
    {
        $group = $this->groups->findOrThrow($groupId);
        if (!$this->groupAcl->canEditInvitations($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Create a new invitation for given group.
     * @POST
     * @Param(name="expireAt", type="post", validation="timestamp|null", description="When the invitation expires.")
     * @Param(name="note", type="post", description="Note for the students who wish to use the invitation link.")
     */
    public function actionCreate($groupId)
    {
        $this->sendSuccessResponse("OK");
    }
}
