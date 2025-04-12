<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Validators\VEmail;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Exceptions\FrontendErrorMappings;
use App\Exceptions\InvalidApiArgumentException;
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
use App\Helpers\MetaFormats\Attributes\Format;
use App\Helpers\MetaFormats\FormatDefinitions\UserFormat;
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

    public function checkCreateAccount()
    {
        if (!$this->registrationConfig->isEnabled()) {
            // If the registration is not enabled in general, creator must be logged in and have privileges.
            if (!$this->userAcl->canCreate()) {
                throw new ForbiddenRequestException();
            }
        }
    }

    /**
     * Create a user account
     * @POST
     * @throws BadRequestException
     * @throws WrongCredentialsException
     * @throws InvalidApiArgumentException
     */
    #[Post("email", new VEmail(), "An email that will serve as a login name")]
    #[Post("firstName", new VString(2), "First name")]
    #[Post("lastName", new VString(2), "Last name")]
    #[Post("password", new VString(1), "A password for authentication")]
    #[Post("passwordConfirm", new VString(1), "A password confirmation")]
    #[Post("instanceId", new VString(1), "Identifier of the instance to register in")]
    #[Post("titlesBeforeName", new VString(1), "Titles which is placed before user name", required: false)]
    #[Post("titlesAfterName", new VString(1), "Titles which is placed after user name", required: false)]
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
            IResponse::S201_Created
        );
    }

    /**
     * Check if the registered E-mail isn't already used and if the password is strong enough
     * @POST
     */
    #[Post("email", new VMixed(), "E-mail address (login name)", nullable: true)]
    #[Post("password", new VMixed(), "Authentication password", required: false, nullable: true)]
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
     * @throws BadRequestException
     * @throws InvalidApiArgumentException
     */
    #[Format(UserFormat::class)]
    public function actionCreateInvitation()
    {
        /** @var UserFormat */
        $format = $this->getFormatInstance();

        // check if the email is free
        $email = trim($format->email);
        // username is name of column which holds login identifier represented by email
        if ($this->logins->getByUsername($email) !== null) {
            throw new BadRequestException("This email address is already taken.");
        }

        $groupsIds = $format->groups ?? [];
        foreach ($groupsIds as $id) {
            $group = $this->groups->get($id);
            if (!$group || $group->isOrganizational() || !$this->groupAcl->canInviteStudents($group)) {
                throw new BadRequestException("Current user cannot invite people in group '$id'");
            }
        }

        // gather data
        $instanceId = $format->instanceId;
        $instance = $this->getInstance($instanceId); // we don't need it, just to check it exists
        $titlesBeforeName = $format->titlesBeforeName === null ? "" : trim($format->titlesBeforeName);
        $titlesAfterName = $format->titlesAfterName === null ? "" : trim($format->titlesAfterName);
        $firstName = trim($format->firstName);
        $lastName = trim($format->lastName);
        if (!$firstName || !$lastName) {
            throw new BadRequestException("The user's full name must be filled in.");
        }

        // Check for name collisions, unless the request explicitly says to ignore them.
        if (!$format->ignoreNameCollision) {
            $sameName = $this->users->findByName($instance, $firstName, $lastName);
            if ($sameName) {
                // let's report the colliding users
                $this->sendSuccessResponse($this->userViewFactory->getUsers($sameName));
                return;
            }
        }

        // create the token and send it via email
        try {
            $this->invitationHelper->invite(
                $instanceId,
                $email,
                $firstName,
                $lastName,
                $titlesBeforeName,
                $titlesAfterName,
                $groupsIds,
                $this->getCurrentUser(),
                $format->locale ?? "en",
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

    /**
     * Accept invitation and create corresponding user account.
     * @POST
     * @throws BadRequestException
     * @throws InvalidApiArgumentException
     */
    #[Post("token", new VString(1), "Token issued in create invitation process.")]
    #[Post("password", new VString(1), "A password for authentication")]
    #[Post("passwordConfirm", new VString(1), "A password confirmation")]
    public function actionAcceptInvitation()
    {
        $req = $this->getRequest();

        // decode and validate invitation token
        try {
            $token = $this->accessManager->decodeInvitationToken($req->getPost("token"));
        } catch (InvalidAccessTokenException $e) {
            throw new BadRequestException(
                "Invalid invitation token",
                FrontendErrorMappings::E400_000__BAD_REQUEST,
                null,
                $e
            );
        }
        if ($token->hasExpired()) {
            throw new BadRequestException(
                "The invitation token has expired",
                FrontendErrorMappings::E400_000__BAD_REQUEST
            );
        }

        // find or create corresponding user
        $login = $this->logins->getByUsername($token->getEmail());
        if ($login === null) {
            // check given passwords
            $password = $req->getPost("password");
            $passwordConfirm = $req->getPost("passwordConfirm");
            if ($password !== $passwordConfirm) {
                throw new WrongCredentialsException(
                    "Provided passwords do not match",
                    FrontendErrorMappings::E400_102__WRONG_CREDENTIALS_PASSWORDS_NOT_MATCH
                );
            }

            // create user entity
            $userData = $token->getUserData();
            $userData[] = Roles::STUDENT_ROLE;
            $userData[] = $this->getInstance($token->getInstanceId());
            $user = new User(...$userData);
            $user->setVerified(); // the invitation token was sent over email -> email is already verified

            // create local login
            Login::createLogin($user, $token->getEmail(), $password, $this->passwordsService);
            $this->users->persist($user);
        } else {
            $user = $login->getUser(); // user already exists
        }

        // add into groups
        $userGroups = []; // user is already in
        foreach ($user->getGroups() as $group) {
            $userGroups[$group->getId()] = $group; // index is ID!
        };

        foreach ($token->getGroupsIds() as $id) {
            if (!empty($userGroups[$id])) {
                continue; // skip groups the user is already involved with
            }

            // deleted, archived, and organizational groups are silently ignored
            $group = $this->groups->get($id);
            if ($group && !$group->isArchived() && !$group->isOrganizational()) {
                $user->makeStudentOf($group);
            }
        }
        $this->groups->flush();

        // return the user entity and a new access token, so the user can log-in right away
        $this->sendSuccessResponse(
            [
                "user" => $this->userViewFactory->getFullUser($user),
                "accessToken" => $this->accessManager->issueToken($user),
            ],
            IResponse::S201_Created
        );
    }
}
