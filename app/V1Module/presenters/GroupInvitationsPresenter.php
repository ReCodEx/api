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
 * Hardware groups endpoints
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

    public function checkDefault($id)
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
        $invitation = $this->groupInvitations->findOrThrow($id);
        $groups = $this->groups->groupsAncestralClosure([ $invitation->getGroup() ]);
        $this->sendSuccessResponse([
            "invitation" => $invitation,
            "groups" => $this->groupViewFactory->getGroups($groups),
        ]);
    }

    public function checkUpdate($id)
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
        $req = $this->getRequest();
        $invitation = $this->groupInvitations->findOrThrow($id);
        $expireAt = $req->getPost("expireAt");
        $invitation->setExpireAt($expireAt ? new DateTime("@" . (int)$expireAt) : null);
        $invitation->setNote($req->getPost("note"));
        $this->groupInvitations->persist($invitation);
        $this->sendSuccessResponse($invitation);
    }

    public function checkRemove($id)
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
        $invitation = $this->groupInvitations->findOrThrow($id);
        $this->groupInvitations->remove($invitation);
        $this->sendSuccessResponse("OK");
    }

    public function checkAccept($id)
    {
        $invitation = $this->groupInvitations->findOrThrow($id);
        if (!$invitation->getGroup() || !$this->groupAcl->canAcceptInvitation($invitation->getGroup())) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Allow the current user to join the corresponding group using the invitation.
     * @POST
     */
    public function actionAccept($id)
    {
        $invitation = $this->groupInvitations->findOrThrow($id);
        $group = $invitation->getGroup();
        $user = $this->getCurrentUser();
        if ($group->isStudentOf($user) === false) {
            $user->makeStudentOf($group);
            $this->groups->flush();
        }
        $this->sendSuccessResponse("OK");
    }

    public function checkList($groupId)
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
        $group = $this->groups->findOrThrow($groupId);
        $this->sendSuccessResponse($group->getInvitations()->toArray());
    }

    public function checkCreate($groupId)
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
        $req = $this->getRequest();
        $group = $this->groups->findOrThrow($groupId);
        $host = $this->getCurrentUser();
        $expireAt = $req->getPost("expireAt");
        $expireAt = $expireAt ? new DateTime("@" . (int)$expireAt) : null;

        $invitation = new GroupInvitation($group, $host, $expireAt, $req->getPost("note"));
        $this->groupInvitations->persist($invitation);
        $this->sendSuccessResponse($invitation);
    }
}
