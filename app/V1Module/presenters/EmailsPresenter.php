<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VString;
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
     */
    #[Post("subject", new VString(1), "Subject for the soon to be sent email")]
    #[Post("message", new VString(1), "Message which will be sent, can be html code")]
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
     */
    #[Post("subject", new VString(1), "Subject for the soon to be sent email")]
    #[Post("message", new VString(1), "Message which will be sent, can be html code")]
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
     */
    #[Post("subject", new VString(1), "Subject for the soon to be sent email")]
    #[Post("message", new VString(1), "Message which will be sent, can be html code")]
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
     * @throws NotFoundException
     * @throws ForbiddenRequestException
     */
    #[Post("toSupervisors", new VBool(), "If true, then the mail will be sent to supervisors", required: false)]
    #[Post("toAdmins", new VBool(), "If the mail should be sent also to primary admins", required: false)]
    #[Post("toObservers", new VBool(), "If the mail should be sent also to observers", required: false)]
    #[Post("toMe", new VBool(), "User wants to also receive an email")]
    #[Post("subject", new VString(1), "Subject for the soon to be sent email")]
    #[Post("message", new VString(1), "Message which will be sent, can be html code")]
    #[Path("groupId", new VString(), required: true)]
    public function actionSendToGroupMembers(string $groupId)
    {
        $this->sendSuccessResponse("OK");
    }
}
