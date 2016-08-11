<?php

namespace App\V1Module\Presenters;

class DefaultPresenter extends BasePresenter {

  /**
   * @GET
   */
  public function actionDefault() {
    $this->sendJson([
      "project" => "ReCodEx API", 
      "version" => "0.3.0",
      "website" => "https://recodex.github.com"
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
    $this->terminate();
  }

}
