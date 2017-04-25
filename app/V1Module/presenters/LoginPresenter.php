<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\WrongCredentialsException;
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


  /**
   * Change the password for the internal authentication system.
   * @POST
   * @LoggedIn
   * @Param(type="post", name="password", required=FALSE, validation="string:1..", description="Old password of current user")
   * @Param(type="post", name="newPassword", required=FALSE, validation="string:1..", description="New password of current user")
   */
  public function actionChangePassword() {
    $req = $this->getRequest();

    // fill user with all provided data
    $login = $this->logins->findCurrent();
    if (!$login) {
      throw new WrongCredentialsException("You are do not use this authentication method so you can't change your password.");
    }

    // passwords need to be handled differently
    $oldPassword = $req->getPost("password");
    $newPassword = $req->getPost("newPassword");
    if ($newPassword) {
      if (($oldPassword !== NULL && !empty($oldPassword) && $login->passwordsMatch($oldPassword)) // old password was provided, just check it against the one from db
        || $this->isInScope(AccessToken::SCOPE_CHANGE_PASSWORD)) { // user is not in modify-password scope and can change password without providing old one
        $login->changePassword($newPassword);
      } else {
        throw new WrongCredentialsException("You are not allowed to change your password.");
      }
    }

    // make password changes permanent
    $this->logins->flush();

    $user = $this->getCurrentUser();
    $this->sendSuccessResponse($user);
  }


}
