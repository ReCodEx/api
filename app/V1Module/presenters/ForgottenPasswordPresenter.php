<?php

namespace App\V1Module\Presenters;

use App\Model\Repository\Logins;

class ForgottenPasswordPresenter extends BasePresenter {

  /**
   * @var Logins
   * @inject
   */
  public $logins;

  /**
   * @POST
   */
  public function actionForgotten() {
    //
  }

  /**
   * @GET
   */
  public function actionIsTokenValid() {
    //
  }

  /**
   * @GET
   */
  public function actionResetToken() {
    //
  }

  /**
   * @POST
   */
  public function actionRenew() {
    //
  }

}
