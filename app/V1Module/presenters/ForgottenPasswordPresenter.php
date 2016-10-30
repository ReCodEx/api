<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Helpers\ForgottenPasswordHelper;
use App\Model\Repository\Logins;
use App\Model\Entity\Login;
use App\Security\AccessToken;

use ZxcvbnPhp\Zxcvbn;

/**
 * Endpoints associated with resetting forgotten passwords
 */
class ForgottenPasswordPresenter extends BasePresenter {

  /**
   * @var Logins
   * @inject
   */
  public $logins;

  /**
   * @var ForgottenPasswordHelper
   * @inject
   */
  public $forgottenPasswordHelper;

  /**
   * Request a password reset (user will receive an e-mail that prompts them to reset their password)
   * @POST
   * @Param(type="post", name="username", validation="string:2..", description="An identifier of the user whose password should be reset")
   */
  public function actionDefault() {
    $req = $this->getHttpRequest();
    $username = $req->getPost("username");

    // try to find login according to username and process request
    $login = $this->logins->findByUsernameOrThrow($username);
    $this->forgottenPasswordHelper->process($login, $req->getRemoteAddress());

    $this->sendSuccessResponse("OK");
  }

  /**
   * Change the user's password
   * @POST
   * @Param(type="post", name="password", validation="string:2..", description="The new password")
   * @LoggedIn
   */
  public function actionChange() {
    $req = $this->getHttpRequest();

    if (!$this->isInScope(AccessToken::SCOPE_CHANGE_PASSWORD)) {
      throw new ForbiddenRequestException("You cannot reset your password with this access token.");
    }

    // try to find login according to username and process request
    $password = $req->getPost("password");
    $login = $this->logins->findCurrent();

    // actually change the password
    $login->setPasswordHash(Login::hashPassword($password));
    $this->logins->persist($login);
    $this->logins->flush();

    $this->sendSuccessResponse("OK");
  }

  /**
   * Check if a password is strong enough
   * @POST
   * @Param(type="post", name="password", description="the password to be checked")
   */
  public function actionValidatePasswordStrength() {
    $req = $this->getHttpRequest();
    $password = $req->getPost("password");

    $zxcvbn = new Zxcvbn;
    $passwordStrength = $zxcvbn->passwordStrength($password);
    $this->sendSuccessResponse([
      "passwordScore" => $passwordStrength["score"]
    ]);
  }

}
