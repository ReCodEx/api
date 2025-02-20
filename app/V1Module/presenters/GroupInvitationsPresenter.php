<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Type;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VEmail;
use App\Helpers\MetaFormats\Validators\VFloat;
use App\Helpers\MetaFormats\Validators\VInt;
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
    #[Path("id", new VString(), required: true)]
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
     */
    #[Post("expireAt", new VTimestamp(), "When the invitation expires.", nullable: true)]
    #[Post("note", new VString(), "Note for the students who wish to use the invitation link.")]
    #[Path("id", new VString(), required: true)]
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
    #[Path("id", new VString(), required: true)]
    public function actionRemove($id)
    {
        $invitation = $this->groupInvitations->findOrThrow($id);
        $this->groupInvitations->remove($invitation);
        $this->sendSuccessResponse("OK");
    }

    public function checkAccept($id)
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
    #[Path("id", new VString(), required: true)]
    public function actionAccept($id)
    {
        $invitation = $this->groupInvitations->findOrThrow($id);
        $group = $invitation->getGroup();
        $user = $this->getCurrentUser();
        if ($group->isStudentOf($user) === false) {
            $user->makeStudentOf($group);
            $this->groups->flush();
        }
        $this->sendSuccessResponse($this->groupViewFactory->getGroup($group));
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
    #[Path("groupId", new VString(), required: true)]
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
     */
    #[Post("expireAt", new VTimestamp(), "When the invitation expires.", nullable: true)]
    #[Post("note", new VString(), "Note for the students who wish to use the invitation link.")]
    #[Path("groupId", new VString(), required: true)]
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
