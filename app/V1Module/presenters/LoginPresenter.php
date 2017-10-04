<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Helpers\ExternalLogin\ExternalServiceAuthenticator;
use App\Model\Entity\User;
use App\Model\Repository\Logins;
use App\Security\AccessToken;
use App\Security\AccessManager;
use App\Security\CredentialsAuthenticator;
use App\Security\Identity;

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
   * Sends response with an access token, if the user exists.
   * @param User $user
   */
  private function sendAccessTokenResponse(User $user) {
    $token = $this->accessManager->issueToken($user, [AccessToken::SCOPE_REFRESH]);
    $this->getUser()->login(new Identity($user, $this->accessManager->decodeToken($token)));

    $this->sendSuccessResponse([
      "accessToken" => $token,
      "user" => $user
    ]);
  }

  /**
   * Log in using user credentials
   * @POST
   * @Param(type="post", name="username", validation="email", description="User's E-mail")
   * @Param(type="post", name="password", validation="string", description="Password")
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
   */
  public function actionExternal($serviceId, $type) {
    $req = $this->getRequest();
    $service = $this->externalServiceAuthenticator->findService($serviceId, $type);
    $user = $this->externalServiceAuthenticator->authenticate($service, $req->getPost());
    $this->sendAccessTokenResponse($user);
  }

  /**
   * Refresh the access token of current user
   * @GET
   * @LoggedIn
   */
  public function actionRefresh() {
    /** @var Identity $identity */
    $identity = $this->getUser()->getIdentity();

    if (!$identity->isInScope(AccessToken::SCOPE_REFRESH)) {
      throw new ForbiddenRequestException();
    }

    $user = $this->getCurrentUser();
    $this->sendSuccessResponse([
      "accessToken" => $this->accessManager->issueToken($user, [AccessToken::SCOPE_REFRESH]),
      "user" => $user
    ]);
  }

}
