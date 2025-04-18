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


    public function checkDefault()
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
        $users = $this->users->findAll();
        $req = $this->getRequest();
        $subject = $req->getPost("subject");
        $message = $req->getPost("message");

        $this->emailLocalizationHelper->sendLocalizedEmail(
            $users,
            function ($toUsers, $emails, $locale) use ($subject, $message) {
                return $this->emailHelper->sendFromDefault([], $locale, $subject, $message, $emails);
            }
        );

        $this->sendSuccessResponse("OK");
    }

    public function checkSendToSupervisors()
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
        $supervisors = $this->users->findByRoles(
            Roles::SUPERVISOR_ROLE,
            Roles::SUPERVISOR_STUDENT_ROLE,
            Roles::EMPOWERED_SUPERVISOR_ROLE,
            Roles::SUPERADMIN_ROLE
        );

        $req = $this->getRequest();
        $subject = $req->getPost("subject");
        $message = $req->getPost("message");

        $this->emailLocalizationHelper->sendLocalizedEmail(
            $supervisors,
            function ($toUsers, $emails, $locale) use ($subject, $message) {
                return $this->emailHelper->sendFromDefault([], $locale, $subject, $message, $emails);
            }
        );

        $this->sendSuccessResponse("OK");
    }

    public function checkSendToRegularUsers()
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
        $users = $this->users->findByRoles(Roles::STUDENT_ROLE, Roles::SUPERVISOR_STUDENT_ROLE);
        $req = $this->getRequest();
        $subject = $req->getPost("subject");
        $message = $req->getPost("message");

        $this->emailLocalizationHelper->sendLocalizedEmail(
            $users,
            function ($toUsers, $emails, $locale) use ($subject, $message) {
                return $this->emailHelper->sendFromDefault([], $locale, $subject, $message, $emails);
            }
        );

        $this->sendSuccessResponse("OK");
    }

    public function checkSendToGroupMembers(string $groupId)
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
        $user = $this->getCurrentUser();
        $group = $this->groups->findOrThrow($groupId);
        $req = $this->getRequest();

        $subject = $req->getPost("subject");
        $message = $req->getPost("message");
        $toSupervisors = filter_var($req->getPost("toSupervisors"), FILTER_VALIDATE_BOOLEAN);
        $toAdmins = filter_var($req->getPost("toAdmins"), FILTER_VALIDATE_BOOLEAN);
        $toObservers = filter_var($req->getPost("toObservers"), FILTER_VALIDATE_BOOLEAN);
        $toMe = filter_var($req->getPost("toMe"), FILTER_VALIDATE_BOOLEAN);

        $users = $group->getStudents()->getValues();
        if ($toSupervisors) {
            $users = array_merge($users, $group->getSupervisors()->getValues());
        }
        if ($toAdmins) {
            $users = array_merge($users, $group->getPrimaryAdmins()->getValues());
        }
        if ($toObservers) {
            $users = array_merge($users, $group->getObservers()->getValues());
        }

        // user requested copy of the email to his/hers email address
        $foundMes = array_filter(
            $users,
            function (User $user) {
                return $user->getId() === $user->getId();
            }
        );
        if ($toMe && count($foundMes) === 0) {
            $users[] = $user;
        }

        $this->emailLocalizationHelper->sendLocalizedEmail(
            $users,
            function ($toUsers, $emails, $locale) use ($subject, $message) {
                return $this->emailHelper->sendFromDefault([], $locale, $subject, $message, $emails);
            }
        );

        $this->sendSuccessResponse("OK");
    }
}
