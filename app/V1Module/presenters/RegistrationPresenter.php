<?php

namespace App\V1Module\Presenters;

use App\Exceptions\FrontendErrorMappings;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\WrongCredentialsException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\BadRequestException;
use App\Exceptions\InvalidAccessTokenException;
use App\Model\Entity\Login;
use App\Model\Entity\User;
use App\Model\Entity\Instance;
use App\Model\Repository\Groups;
use App\Model\Repository\Logins;
use App\Model\Repository\ExternalLogins;
use App\Model\Repository\Instances;
use App\Model\View\UserViewFactory;
use App\Security\AccessManager;
use App\Helpers\ExternalLogin\ExternalServiceAuthenticator;
use App\Helpers\EmailVerificationHelper;
use App\Helpers\RegistrationConfig;
use App\Helpers\InvitationHelper;
use App\Security\Roles;
use App\Security\ACL\IUserPermissions;
use App\Security\ACL\IGroupPermissions;
use Nette\Http\IResponse;
use Nette\Security\Passwords;
use ZxcvbnPhp\Zxcvbn;

/**
 * Registration management endpoints
 */
class RegistrationPresenter extends BasePresenter
{
    /**
     * @var Logins
     * @inject
     */
    public $logins;

    /**
     * @var ExternalLogins
     * @inject
     */
    public $externalLogins;

    /**
     * @var AccessManager
     * @inject
     */
    public $accessManager;

    /**
     * @var Instances
     * @inject
     */
    public $instances;

    /**
     * @var Groups
     * @inject
     */
    public $groups;

    /**
     * @var ExternalServiceAuthenticator
     * @inject
     */
    public $externalServiceAuthenticator;

    /**
     * @var EmailVerificationHelper
     * @inject
     */
    public $emailVerificationHelper;

    /**
     * @var UserViewFactory
     * @inject
     */
    public $userViewFactory;

    /**
     * @var IUserPermissions
     * @inject
     */
    public $userAcl;

    /**
     * @var IGroupPermissions
     * @inject
     */
    public $groupAcl;

    /**
     * @var RegistrationConfig
     * @inject
     */
    public $registrationConfig;

    /**
     * @var Passwords
     * @inject
     */
    public $passwordsService;

    /**
     * @var InvitationHelper
     * @inject
     */
    public $invitationHelper;

    /**
     * Get an instance by its ID.
     * @param string $instanceId
     * @return Instance
     * @throws BadRequestException
     */
    protected function getInstance(string $instanceId): Instance
    {
        $instance = $this->instances->get($instanceId);
        if (!$instance) {
            throw new BadRequestException("Instance '$instanceId' does not exist.");
        } else {
            if (!$instance->isOpen()) {
                throw new BadRequestException("This instance is not open, you cannot register here.");
            }
        }

        return $instance;
    }

    public function noncheckCreateAccount()
    {
        if (!$this->registrationConfig->isEnabled()) {
            // If the registration is not enabled in general, creator must be logged in and have priviledges.
            if (!$this->userAcl->canCreate()) {
                throw new ForbiddenRequestException();
            }
        }
    }

    /**
     * Create a user account
     * @POST
     * @Param(type="post", name="email", validation="email", description="An email that will serve as a login name")
     * @Param(type="post", name="firstName", validation="string:2..", description="First name")
     * @Param(type="post", name="lastName", validation="string:2..", description="Last name")
     * @Param(type="post", name="password", validation="string:1..", msg="Password cannot be empty.",
     *        description="A password for authentication")
     * @Param(type="post", name="passwordConfirm", validation="string:1..", msg="Confirm Password cannot be empty.",
     *        description="A password confirmation")
     * @Param(type="post", name="instanceId", validation="string:1..",
     *        description="Identifier of the instance to register in")
     * @Param(type="post", name="titlesBeforeName", required=false, validation="string:1..",
     *        description="Titles which is placed before user name")
     * @Param(type="post", name="titlesAfterName", required=false, validation="string:1..",
     *        description="Titles which is placed after user name")
     * @throws BadRequestException
     * @throws WrongCredentialsException
     * @throws InvalidArgumentException
     */
    public function actionCreateAccount()
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Check if the registered E-mail isn't already used and if the password is strong enough
     * @POST
     * @Param(type="post", name="email", description="E-mail address (login name)")
     * @Param(type="post", name="password", required=false, description="Authentication password")
     */
    public function actionValidateRegistrationData()
    {
        $this->sendSuccessResponse("OK");
    }

    public function noncheckCreateInvitation()
    {
        if (!$this->userAcl->canInviteForRegistration()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Create an invitation for a user and send it over via email
     * @POST
     * @Param(type="post", name="email", validation="email", description="An email that will serve as a login name")
     * @Param(type="post", name="firstName", required=true, validation="string:2..", description="First name")
     * @Param(type="post", name="lastName", validation="string:2..", description="Last name")
     * @Param(type="post", name="instanceId", validation="string:1..",
     *        description="Identifier of the instance to register in")
     * @Param(type="post", name="titlesBeforeName", required=false, validation="string:1..",
     *        description="Titles which is placed before user name")
     * @Param(type="post", name="titlesAfterName", required=false, validation="string:1..",
     *        description="Titles which is placed after user name")
     * @Param(type="post", name="groups", required=false, validation="array",
     *        description="List of group IDs in which the user is added right after registration")
     * @Param(type="post", name="locale", required=false, validation="string:2",
     *        description="Language used in the invitation email (en by default).")
     * @throws BadRequestException
     * @throws InvalidArgumentException
     */
    public function actionCreateInvitation()
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Accept invitation and create corresponding user account.
     * @POST
     * @Param(type="post", name="token", validation="string:1..",
     *        description="Token issued in create invitation process.")
     * @Param(type="post", name="password", validation="string:1..", msg="Password cannot be empty.",
     *        description="A password for authentication")
     * @Param(type="post", name="passwordConfirm", validation="string:1..", msg="Confirm Password cannot be empty.",
     *        description="A password confirmation")
     * @throws BadRequestException
     * @throws InvalidArgumentException
     */
    public function actionAcceptInvitation()
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Endpoint for performance testing.
     * @POST
     * @param int $a
     * @Param(type="query", name="b", validation="email")
     * @Param(type="post", name="c", validation="float")
     * @throws BadRequestException
     * @throws InvalidArgumentException
     */
    public function actionTestLoose(int $a, ?string $email)
    {
        $this->sendSuccessResponse("OK");
    }


    /**
     * Endpoint for performance testing.
     * @POST
     * @param string $a
     * @param string $b
     * @param ?string $c
     * @param ?string $d
     * @param ?string $e
     * @throws BadRequestException
     * @throws InvalidArgumentException
     */
    public function actionTest5UrlLoose(string $a, string $b, ?string $c, ?string $d, ?string $e)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Endpoint for performance testing.
     * @POST
     * @Param(type="post", name="a", validation="string")
     * @Param(type="post", name="b", validation="string")
     * @Param(type="post", name="c", validation="string")
     * @Param(type="post", name="d", validation="string")
     * @Param(type="post", name="e", validation="string")
     * @throws BadRequestException
     * @throws InvalidArgumentException
     */
    public function actionTest5BodyLoose()
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Endpoint for performance testing.
     * @POST
     * @Param(type="post", name="a", validation="string")
     * @Param(type="post", name="nested")
     * @throws BadRequestException
     * @throws InvalidArgumentException
     */
    public function actionTest5BodyLooseNested()
    {
        $this->sendSuccessResponse("OK");
    }
}
