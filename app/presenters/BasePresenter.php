<?php

namespace App\Presenters;

use Nette;
use App\Model;


/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{
  public function startup() {
    parent::startup();
    
    // take care of preflight requests - terminate them right away with a 200 response
    $req = $this->getHttpRequest();
    if ($req->isMethod('OPTIONS')) {
      $res = $this->getHttpResponse();
      $res->setHeader('Access-Control-Allow-Origin', '*');
      $res->setHeader('Access-Control-Allow-Headers', $req->getHeader('Access-Control-Request-Headers'));
      $this->terminate();
    }
  }
}
