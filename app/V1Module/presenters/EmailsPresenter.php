<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\NotFoundException;
use App\Helpers\EmailHelper;
use App\Helpers\Emails\EmailLocalizationHelper;
use App\Model\Entity\User;
use App\Model\Repository\Groups;
use App\Security\ACL\IEmailPermissions;
use App\Security\ACL\IGroupPermissions;
use App\Security\Roles;

class EmailsPresenter extends BasePresenter
{
    /**
     * @var EmailLocalizationHelper
     * @inject
     */
    public $emailLocalizationHelper;

    /**
     * @var EmailHelper
     * @inject
     */
    public $emailHelper;

    /**
     * @var IEmailPermissions
     * @inject
     */
    public $emailAcl;

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


    public function noncheckDefault()
    {
        if (!$this->emailAcl->canSendToAll()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Sends an email with provided subject and message to all ReCodEx users.
     * @POST
     * @Param(type="post", name="subject", validation="string:1..", description="Subject for the soon to be sent email")
     * @Param(type="post", name="message", validation="string:1..",
     *        description="Message which will be sent, can be html code")
     */
    public function actionDefault()
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSendToSupervisors()
    {
        if (!$this->emailAcl->canSendToSupervisors()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Sends an email with provided subject and message to all supervisors and superadmins.
     * @POST
     * @Param(type="post", name="subject", validation="string:1..", description="Subject for the soon to be sent email")
     * @Param(type="post", name="message", validation="string:1..",
     *        description="Message which will be sent, can be html code")
     */
    public function actionSendToSupervisors()
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSendToRegularUsers()
    {
        if (!$this->emailAcl->canSendToRegularUsers()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Sends an email with provided subject and message to all regular users.
     * @POST
     * @Param(type="post", name="subject", validation="string:1..", description="Subject for the soon to be sent email")
     * @Param(type="post", name="message", validation="string:1..",
     *        description="Message which will be sent, can be html code")
     */
    public function actionSendToRegularUsers()
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckSendToGroupMembers(string $groupId)
    {
        $group = $this->groups->findOrThrow($groupId);
        if (!$this->groupAcl->canSendEmail($group)) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Sends an email with provided subject and message to regular members of
     * given group and optionally to supervisors and admins.
     * @POST
     * @param string $groupId
     * @Param(type="post", name="toSupervisors", validation="bool", required=false,
     *        description="If true, then the mail will be sent to supervisors")
     * @Param(type="post", name="toAdmins", validation="bool", required=false,
     *        description="If the mail should be sent also to primary admins")
     * @Param(type="post", name="toObservers", validation="bool", required=false,
     *        description="If the mail should be sent also to observers")
     * @Param(type="post", name="toMe", validation="bool", description="User wants to also receive an email")
     * @Param(type="post", name="subject", validation="string:1..", description="Subject for the soon to be sent email")
     * @Param(type="post", name="message", validation="string:1..",
     *        description="Message which will be sent, can be html code")
     * @throws NotFoundException
     * @throws ForbiddenRequestException
     */
    public function actionSendToGroupMembers(string $groupId)
    {
        $this->sendSuccessResponse("OK");
    }
}
