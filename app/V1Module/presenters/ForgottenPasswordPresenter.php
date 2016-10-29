<?php

namespace App\V1Module\Presenters;

use App\Exceptions\ForbiddenRequestException;
use App\Helpers\ForgottenPasswordHelper;
use App\Model\Repository\Logins;
use App\Model\Entity\Login;
use App\Security\AccessToken;

use ZxcvbnPhp\Zxcvbn;

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
   * @POST
   * @Param(type="post", name="username", validation="string:2..")
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
   * @POST
   * @Param(type="post", name="password", validation="string:2..")
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
   * @POST
   * @Param(type="post", name="password")
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
