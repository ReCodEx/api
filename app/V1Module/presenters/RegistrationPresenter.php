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

    public function checkCreateAccount()
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
        $req = $this->getRequest();

        // check if the email is free
        $email = trim($req->getPost("email"));
        // username is name of column which holds login identifier represented by email
        if ($this->logins->getByUsername($email) !== null) {
            throw new BadRequestException("This email address is already taken.");
        }

        $instanceId = $req->getPost("instanceId");
        $instance = $this->getInstance($instanceId);

        $titlesBeforeName = $req->getPost("titlesBeforeName") === null ? "" : $req->getPost("titlesBeforeName");
        $titlesAfterName = $req->getPost("titlesAfterName") === null ? "" : $req->getPost("titlesAfterName");

        // check given passwords
        $password = $req->getPost("password");
        $passwordConfirm = $req->getPost("passwordConfirm");
        if ($password !== $passwordConfirm) {
            throw new WrongCredentialsException(
                "Provided passwords do not match",
                FrontendErrorMappings::E400_102__WRONG_CREDENTIALS_PASSWORDS_NOT_MATCH
            );
        }

        $user = new User(
            $email,
            $req->getPost("firstName"),
            $req->getPost("lastName"),
            $titlesBeforeName,
            $titlesAfterName,
            Roles::STUDENT_ROLE,
            $instance
        );

        Login::createLogin($user, $email, $password, $this->passwordsService);
        $this->users->persist($user);

        // email verification
        $this->emailVerificationHelper->process($user, true); // true = account has just been created

        // add new user to implicit groups
        foreach ($this->registrationConfig->getImplicitGroupsIds() as $groupId) {
            $group = $this->groups->findOrThrow($groupId);
            if (!$group->isArchived() && !$group->isOrganizational()) {
                $user->makeStudentOf($group);
            }
        }
        $this->groups->flush();

        // successful!
        $this->sendSuccessResponse(
            [
                "user" => $this->userViewFactory->getFullUser($user),
                "accessToken" => $this->accessManager->issueToken($user)
            ],
            IResponse::S201_CREATED
        );
    }

    /**
     * Check if the registered E-mail isn't already used and if the password is strong enough
     * @POST
     * @Param(type="post", name="email", description="E-mail address (login name)")
     * @Param(type="post", name="password", required=false, description="Authentication password")
     */
    public function actionValidateRegistrationData()
    {
        $req = $this->getRequest();
        $email = $req->getPost("email");
        $emailParts = explode("@", $email);
        $password = $req->getPost("password");

        $response = [
            "usernameIsFree" => $this->users->getByEmail($email) === null
        ];

        if ($password) {
            $zxcvbn = new Zxcvbn();
            $passwordStrength = $zxcvbn->passwordStrength($password, [$email, $emailParts[0]]);
            $response["passwordScore"] = $passwordStrength["score"];
        }

        $this->sendSuccessResponse($response);
    }

    public function checkCreateInvitation()
    {
        if (!$this->userAcl->canInviteForRegistration()) {
            throw new ForbiddenRequestException();
        }
    }

    /**
     * Create an invitation for a user and send it over via email
     * @POST
     * @Param(type="post", name="email", validation="email", description="An email that will serve as a login name")
     * @Param(type="post", name="firstName", validation="string:2..", description="First name")
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
        $req = $this->getRequest();

        // check if the email is free
        $email = trim($req->getPost("email"));
        // username is name of column which holds login identifier represented by email
        if ($this->logins->getByUsername($email) !== null) {
            throw new BadRequestException("This email address is already taken.");
        }

        // gather data
        $instanceId = $req->getPost("instanceId");
        $instance = $this->getInstance($instanceId);
        $titlesBeforeName = $req->getPost("titlesBeforeName") === null ? "" : $req->getPost("titlesBeforeName");
        $titlesAfterName = $req->getPost("titlesAfterName") === null ? "" : $req->getPost("titlesAfterName");

        // create the token and send it via email
        try {
            $this->invitationHelper->invite(
                $instanceId,
                $email,
                $req->getPost("firstName"),
                $req->getPost("lastName"),
                $titlesBeforeName,
                $titlesAfterName,
                $req->getPost("groups") ?? [],
                $this->getCurrentUser(),
                $req->getPost("locale") ?? "en",
            );
        } catch (InvalidAccessTokenException $e) {
            throw new BadRequestException(
                "Invalid invitation data",
                FrontendErrorMappings::E400_000__BAD_REQUEST,
                null,
                $e
            );
        }

        $this->sendSuccessResponse("OK");
    }
}
