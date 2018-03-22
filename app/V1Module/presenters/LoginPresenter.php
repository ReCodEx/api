<?php

namespace App\V1Module\Presenters;

use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidAccessTokenException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\WrongCredentialsException;
use App\Helpers\ExternalLogin\ExternalServiceAuthenticator;
use App\Model\Entity\User;
use App\Model\Repository\Logins;
use App\Model\View\UserViewFactory;
use App\Security\AccessToken;
use App\Security\AccessManager;
use App\Security\ACL\IUserPermissions;
use App\Security\CredentialsAuthenticator;
use App\Security\Identity;
use Nette\Security\AuthenticationException;

/**
 * Endpoints used to log a user in
 */
class LoginPresenter extends BasePresenter {
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
   * Sends response with an access token, if the user exists.
   * @param User $user
   * @throws AuthenticationException
   * @throws ForbiddenRequestException
   * @throws InvalidAccessTokenException
   */
  private function sendAccessTokenResponse(User $user) {
    $token = $this->accessManager->issueToken($user, [AccessToken::SCOPE_MASTER, AccessToken::SCOPE_REFRESH]);
    $this->getUser()->login(new Identity($user, $this->accessManager->decodeToken($token)));

    $this->sendSuccessResponse([
      "accessToken" => $token,
      "user" => $this->userViewFactory->getFullUser($user)
    ]);
  }

  /**
   * Log in using user credentials
   * @POST
   * @Param(type="post", name="username", validation="email:1..", description="User's E-mail")
   * @Param(type="post", name="password", validation="string:1..", description="Password")
   * @throws AuthenticationException
   * @throws ForbiddenRequestException
   * @throws InvalidAccessTokenException
   * @throws WrongCredentialsException
   */
  public function actionDefault() {
    $req = $this->getRequest();
    $username = $req->getPost("username");
    $password = $req->getPost("password");

    $user = $this->credentialsAuthenticator->authenticate($username, $password);
    $this->sendAccessTokenResponse($user);
  }

  /**
   * Log in using an external authentication service
   * @POST
   * @param string $serviceId Identifier of the login service
   * @param string $type Type of the authentication process
   * @throws AuthenticationException
   * @throws ForbiddenRequestException
   * @throws InvalidAccessTokenException
   * @throws WrongCredentialsException
   * @throws BadRequestException
   */
  public function actionExternal($serviceId, $type) {
    $req = $this->getRequest();
    $service = $this->externalServiceAuthenticator->findService($serviceId, $type);
    $user = $this->externalServiceAuthenticator->authenticate($service, $req->getPost());
    $this->sendAccessTokenResponse($user);
  }

  public function checkTakeOver($userId) {
    $user = $this->users->findOrThrow($userId);
    if (!$this->userAcl->canTakeOver($user)) {
      throw new ForbiddenRequestException();
    }
  }

  /**
   * Takeover user account with specified user identification.
   * @POST
   * @LoggedIn
   * @param $userId
   * @throws AuthenticationException
   * @throws ForbiddenRequestException
   * @throws InvalidAccessTokenException
   */
  public function actionTakeOver($userId) {
    $user = $this->users->findOrThrow($userId);
    $this->sendAccessTokenResponse($user);
  }

  /**
   * @throws ForbiddenRequestException
   * @throws InvalidArgumentException
   */
  public function checkRefresh() {
    if (!$this->isInScope(AccessToken::SCOPE_REFRESH)) {
      throw new ForbiddenRequestException(sprintf("Only tokens in the '%s' scope can be refreshed", AccessToken::SCOPE_REFRESH));
    }

  }

  /**
   * Refresh the access token of current user
   * @GET
   * @LoggedIn
   * @throws ForbiddenRequestException
   */
  public function actionRefresh() {
    $token = $this->getAccessToken();

    $user = $this->getCurrentUser();
    $this->sendSuccessResponse([
      "accessToken" => $this->accessManager->issueToken($user, $token->getScopes(), $token->getExpirationTime()),
      "user" => $this->userViewFactory->getFullUser($user)
    ]);
  }

  public function checkIssueToken() {
    if (!$this->getAccessToken()->isInScope(AccessToken::SCOPE_MASTER)) {
      throw new ForbiddenRequestException("Restricted tokens cannot be used to issue new tokens");
    }
  }

  /**
   * Issue a new access token with a restricted set of scopes
   * @POST
   * @LoggedIn
   * @Param(type="post", name="scopes", validation="list", description="A list of requested scopes")
   * @Param(type="post", required=false, name="expiration", validation="integer", description="How long the token should be valid (in seconds)")
   * @throws ForbiddenRequestException
   */
  public function actionIssueToken() {
    $request = $this->getRequest();
    // The scopes are not filtered in any way - the ACL won't allow anything that the user cannot do in a full session
    $scopes = $request->getPost("scopes");

    if (in_array(AccessToken::SCOPE_MASTER, $scopes)) {
      throw new ForbiddenRequestException("Master tokens can only be issued through the login endpoint");
    }

    $expiration = $request->getPost("expiration") !== null ? intval($request->getPost("expiration")) : null;
    $user = $this->getCurrentUser();

    $this->sendSuccessResponse([
      "accessToken" => $this->accessManager->issueToken($user, $scopes, $expiration),
      "user" => $this->userViewFactory->getFullUser($user)
    ]);
  }

}
