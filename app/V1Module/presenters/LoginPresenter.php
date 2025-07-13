<?php

namespace App\V1Module\Presenters;

use App\Helpers\MetaFormats\Attributes\Post;
use App\Helpers\MetaFormats\Attributes\Path;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VEmail;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VString;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\FrontendErrorMappings;
use App\Exceptions\InvalidAccessTokenException;
use App\Exceptions\InvalidApiArgumentException;
use App\Exceptions\WrongCredentialsException;
use App\Helpers\ExternalLogin\ExternalServiceAuthenticator;
use App\Model\Entity\SecurityEvent;
use App\Model\Entity\User;
use App\Model\Repository\Logins;
use App\Model\Repository\SecurityEvents;
use App\Model\Repository\Users;
use App\Model\View\UserViewFactory;
use App\Security\AccessManager;
use App\Security\ACL\IUserPermissions;
use App\Security\CredentialsAuthenticator;
use App\Security\Identity;
use App\Security\Roles;
use App\Security\TokenScope;
use Nette\Security\AuthenticationException;
use Nette\Http\IResponse;

/**
 * Endpoints used to log a user in
 */
class LoginPresenter extends BasePresenter
{
    /**
     * @var AccessManager
     * @inject
     */
    public $accessManager;

    /**
     * @var CredentialsAuthenticator
     * @inject
     */
    public $credentialsAuthenticator;

    /**
     * @var ExternalServiceAuthenticator
     * @inject
     */
    public $externalServiceAuthenticator;

    /**
     * @var Logins
     * @inject
     */
    public $logins;

    /**
     * @var SecurityEvents
     * @inject
     */
    public $securityEvents;

    /**
     * @var Users
     * @inject
     */
    public $users;

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
     * @var Roles
     * @inject
     */
    public $roles;


    /**
     * Sends response with an access token, if the user exists.
     * @param User $user
     * @throws AuthenticationException
     * @throws ForbiddenRequestException
     * @throws InvalidAccessTokenException
     */
    private function sendAccessTokenResponse(User $user)
    {
        $token = $this->accessManager->issueToken($user, null, [TokenScope::MASTER, TokenScope::REFRESH]);
        $this->getUser()->login(new Identity($user, $this->accessManager->decodeToken($token)));

        $this->sendSuccessResponse(
            [
                "accessToken" => $token,
                "user" => $this->userViewFactory->getFullUser($user)
            ]
        );
    }

    /**
     * Log in using user credentials
     * @POST
     * @throws AuthenticationException
     * @throws ForbiddenRequestException
     * @throws InvalidAccessTokenException
     * @throws WrongCredentialsException
     */
    #[Post("username", new VEmail(), "User's E-mail")]
    #[Post("password", new VString(1), "Password")]
    public function actionDefault()
    {
        $this->sendSuccessResponse("OK");
    }

    /**
     * Log in using an external authentication service
     * @POST
     * @throws AuthenticationException
     * @throws ForbiddenRequestException
     * @throws InvalidAccessTokenException
     * @throws WrongCredentialsException
     * @throws BadRequestException
     */
    #[Post("token", new VString(1), "JWT external authentication token")]
    #[Path("authenticatorName", new VString(), "Identifier of the external authenticator", required: true)]
    public function actionExternal($authenticatorName)
    {
        $this->sendSuccessResponse("OK");
    }

    // public function checkTakeOver($userId)
    // {
    //     $user = $this->users->findOrThrow($userId);
    //     if (!$this->userAcl->canTakeOver($user)) {
    //         throw new ForbiddenRequestException();
    //     }
    // }

    /**
     * Takeover user account with specified user identification.
     * @POST
     * @LoggedIn
     * @throws AuthenticationException
     * @throws ForbiddenRequestException
     * @throws InvalidAccessTokenException
     */
    #[Path("userId", new VString(), required: true)]
    public function actionTakeOver($userId)
    {
        $this->sendSuccessResponse("OK");
    }

    // /**
    //  * @throws ForbiddenRequestException
    //  */
    // public function checkRefresh()
    // {
    //     if (!$this->isInScope(TokenScope::REFRESH)) {
    //         throw new ForbiddenRequestException(
    //             sprintf("Only tokens in the '%s' scope can be refreshed", TokenScope::REFRESH)
    //         );
    //     }
    // }

    /**
     * Refresh the access token of current user
     * @POST
     * @LoggedIn
     * @throws ForbiddenRequestException
     */
    public function actionRefresh()
    {
        $this->sendSuccessResponse("OK");
    }

    // public function checkIssueRestrictedToken()
    // {
    //     if (!$this->getAccessToken()->isInScope(TokenScope::MASTER)) {
    //         throw new ForbiddenRequestException("Restricted tokens cannot be used to issue new tokens");
    //     }
    // }

    /**
     * Issue a new access token with a restricted set of scopes
     * @POST
     * @LoggedIn
     * @throws BadRequestException
     * @throws ForbiddenRequestException
     * @throws InvalidApiArgumentException
     */
    #[Post("effectiveRole", new VString(), "Effective user role contained within issued token", required: false)]
    #[Post("scopes", new VArray(), "A list of requested scopes")]
    #[Post("expiration", new VInt(), "How long should the token be valid (in seconds)", required: false)]
    public function actionIssueRestrictedToken()
    {
        $this->sendSuccessResponse("OK");
    }

    private function validateScopeRoles(?array $scopes, $expiration)
    {
        $forbiddenScopes = [
            TokenScope::CHANGE_PASSWORD =>
            "Password change tokens can only be issued through the password reset endpoint",
            TokenScope::EMAIL_VERIFICATION => "E-mail verification tokens must be received via e-mail",
        ];

        // check if any of given scopes is among the forbidden ones
        $violations = array_intersect(array_keys($forbiddenScopes), $scopes);
        if ($violations) {
            throw new ForbiddenRequestException($forbiddenScopes[$violations[0]]);
        }

        $restrictedScopes = [
            TokenScope::MASTER => $this->accessManager->getExpiration()
        ];

        foreach (array_intersect(array_keys($restrictedScopes), $scopes) as $match) {
            if ($expiration !== null && $restrictedScopes[$match] < $expiration) {
                throw new ForbiddenRequestException(
                    sprintf(
                        "Cannot issue token with scope '%s' and expiration period longer than '%d' seconds",
                        $match,
                        $restrictedScopes[$match]
                    )
                );
            }
        }
    }

    private function validateEffectiveRole(?string $effectiveRole)
    {
        if ($effectiveRole == null) {
            return;
        }

        if (!$this->roles->validateRole($effectiveRole)) {
            throw new InvalidApiArgumentException('effectiveRole', "Unknown user role '$effectiveRole'");
        }

        $role = $this->getCurrentUser()->getRole();
        if (!$this->roles->isInRole($role, $effectiveRole)) {
            throw new BadRequestException(
                "Cannot issue token with effective role '$effectiveRole' higher than the actual one '$role'",
                FrontendErrorMappings::E400_002__BAD_REQUEST_FORBIDDEN_EFFECTIVE_ROLE,
                ["effectiveRole" => $effectiveRole, "role" => $role]
            );
        }
    }
}
