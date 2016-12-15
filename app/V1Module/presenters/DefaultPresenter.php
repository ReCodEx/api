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

  /**
   * Take care of preflight requests - terminate them right away with a 200 response
   */
  public function actionPreflight() {
    $req = $this->getHttpRequest();
    $res = $this->getHttpResponse();
    $res->setHeader('Access-Control-Allow-Origin', '*');
    $res->setHeader('Access-Control-Allow-Headers', $req->getHeader('Access-Control-Request-Headers'));
    $res->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
    $this->terminate();
  }

}
