<?php

namespace App\V1Module\Presenters;

class DefaultPresenter extends BasePresenter {

  /**
   * @GET
   */
  public function actionDefault() {
    $this->sendJson([
      'project' => 'ReCodEx API', 
      'version' => '1.0.0',
      'website' => 'https://recodex.github.com'
    ]);
  }

}
