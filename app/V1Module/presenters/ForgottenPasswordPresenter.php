<?php

namespace App\V1Module\Presenters;

use App\Model\Repository\Logins;
use App\Helpers\ForgottenPasswordHelper;

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
   * @Param(type="post", name="redirectUrl", validation="string:2..")
   */
  public function actionDefault() {
    $req = $this->getHttpRequest();
    $username = $req->getPost("username");
    $redirectUrl = $req->getPost("redirectUrl");

    // try to find login according to username and process request
    $login = $this->logins->findByUsernameOrThrow($username);
    $this->forgottenPasswordHelper->process($login, $redirectUrl);

    $this->sendSuccessResponse("OK");
  }

}
