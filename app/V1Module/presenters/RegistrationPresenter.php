<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\FormatDefinitions\Test5BodyFormat;
use App\Helpers\MetaFormats\Validators\VBool;
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
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Attributes\Query;
use App\Helpers\MetaFormats\FormatDefinitions\Test5UrlFormat;
use App\Helpers\MetaFormats\FormatDefinitions\UserFormat;
use App\Helpers\MetaFormats\FormatDefinitions\TestFormat;
use App\Helpers\MetaFormats\FormatDefinitions\TestNested1Format;
use App\Helpers\MetaFormats\Validators\VDouble;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VUuid;
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
    #[Post(
        "ignoreNameCollision",
        new VBool(),
        "If a use with the same name exists, this needs to be set to true.",
        required: false
    )]
    public function actionCreateAccount()
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Check if the registered E-mail isn't already used and if the password is strong enough
     * @POST
     */
    #[Post("email", new VMixed(), "E-mail address (login name)", nullable: true)]
    #[Post("password", new VMixed(), "Authentication password", required: false, nullable: true)]
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
     * @throws BadRequestException
     * @throws InvalidApiArgumentException
     */
    #[Format(UserFormat::class)]
    public function actionCreateInvitation()
    {
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
        $this->sendSuccessResponse("OK");
    }

    /**
     * Endpoint for performance testing.
     * @POST
     * @throws BadRequestException
     * @throws InvalidArgumentException
     */
    #[Path("a", new VInt())]
    #[Query("b", new VEmail())]
    #[Post("c", new VDouble())]
    public function actionTestLoose(int $a, ?string $b)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Endpoint for performance testing.
     * @POST
     * @throws BadRequestException
     * @throws InvalidArgumentException
     */
    #[Format(TestFormat::class)]
    public function actionTestFormat(int $a, ?string $b)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Endpoint for performance testing.
     * @POST
     * @throws BadRequestException
     * @throws InvalidArgumentException
     */
    #[Path("a", new VString())]
    #[Path("b", new VString())]
    #[Query("c", new VString())]
    #[Query("d", new VString())]
    #[Query("e", new VString())]
    public function actionTest5UrlLoose(string $a, string $b, ?string $c, ?string $d, ?string $e)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Endpoint for performance testing.
     * @POST
     * @throws BadRequestException
     * @throws InvalidArgumentException
     */
    #[Format(Test5UrlFormat::class)]
    public function actionTest5UrlFormat(string $a, string $b, ?string $c, ?string $d, ?string $e)
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Endpoint for performance testing.
     * @POST
     * @throws BadRequestException
     * @throws InvalidArgumentException
     */
    #[Post("a", new VString())]
    #[Post("b", new VString())]
    #[Post("c", new VString())]
    #[Post("d", new VString())]
    #[Post("e", new VString())]
    public function actionTest5BodyLoose()
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Endpoint for performance testing.
     * @POST
     * @throws BadRequestException
     * @throws InvalidArgumentException
     */
    #[Format(Test5BodyFormat::class)]
    public function actionTest5BodyFormat()
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Endpoint for performance testing.
     * @POST
     * @throws BadRequestException
     * @throws InvalidArgumentException
     */
    #[Format(TestNested1Format::class)]
    public function actionTest5BodyFormatNested()
    {
        $this->sendSuccessResponse("OK");
    }
}
