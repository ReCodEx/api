<?php

namespace App\V1Module\Presenters;

use App\Helpers\ApiConfig;

class DefaultPresenter extends BasePresenter {

  /**
   * @var ApiConfig
   * @inject
   */
  public $apiConfig;

  /**
   * @GET
   */
  public function actionDefault() {
    $this->sendJson([
      "project" => $this->apiConfig->getName(),
      "version" => $this->apiConfig->getVersion(),
      "website" => $this->apiConfig->getAddress()
    ]);
  }
}
