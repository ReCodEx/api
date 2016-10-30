<?php

namespace App\V1Module\Presenters;

use App\Exceptions\WrongCredentialsException;
use App\Exceptions\BadRequestException;
use App\Helpers\ExternalLogin\IExternalLoginService;
use App\Helpers\ExternalLogin\AuthService;
use App\Model\Entity\User;
use App\Model\Repository\Logins;
use App\Model\Repository\ExternalLogins;
use App\Security\AccessToken;
use App\Security\AccessManager;

/**
 * Endpoints used to log a user in
 */
class LoginPresenter extends BasePresenter {

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
   * @var AuthService
   * @inject
   */
  public $authService;

  /**
   * Sends response with an access token, if the user exists.
   * @param User $user
   * @throws WrongCredentialsException
   */
  private function trySendingLoggedInResponse($user) {
    if (!$user) {
      throw new WrongCredentialsException;
    }

    $this->sendSuccessResponse([
      "accessToken" => $this->accessManager->issueToken($user, [ AccessToken::SCOPE_REFRESH ]),
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
    $req = $this->getHttpRequest();
    $username = $req->getPost("username");
    $password = $req->getPost("password");

    $user = $this->logins->getUser($username, $password);
    $this->trySendingLoggedInResponse($user);
  }

  /**
   * Log in using an external authentication service
   * @POST
   * @Param(type="post", name="username", validation="string", description="User name")
   * @Param(type="post", name="password", validation="string", description="Password")
   */
  public function actionExternal($serviceId) {
    $req = $this->getHttpRequest();
    $username = $req->getPost("username");
    $password = $req->getPost("password");

    $authService = $this->authService->getById($serviceId);
    $externalData = $authService->getUser($username, $password); // throws if the user cannot be logged in
    $user = $this->externalLogins->getUser($serviceId, $externalData->getId());
    $this->trySendingLoggedInResponse($user);
  }

  /**
   * Refresh the access token of current user
   * @GET
   * @LoggedIn
   */
  public function actionRefresh() {
    $user = $this->users->findCurrentUserOrThrow();
    $this->sendSuccessResponse([
      "accessToken" => $this->accessManager->issueToken($user),
      "user" => $user
    ]);
  }

}
