<?php

namespace App\V1Module\Presenters;

use ReflectionException;
use App\Exception\NotFoundException;
use App\Exception\BadRequestException;
use App\Exception\ForbiddenRequestException;
use App\Exception\WrongHttpMethodException;
use App\Exception\NotImplementedException;

use App\Model\Repository\Users;

use Nette\Application\Application;
use Nette\Http\IResponse;
use Nette\Reflection;

class BasePresenter extends \App\Presenters\BasePresenter {
  
  /** @inject @var Users */
  public $users;
  
  /** @inject @var Application */
  public $application;

  public function startup() {
    parent::startup();
    $this->application->errorPresenter = "V1:ApiError";

    // checks, if the action has allowed method annotation
    try {
      $reflection = new Reflection\ClassType(get_class($this));
      $actionMethodName = $this->formatActionMethod($this->getAction());
      $httpMethod = $this->getHttpRequest()->getMethod();
      if ($reflection->getMethod($actionMethodName)->hasAnnotation($httpMethod) === FALSE) {
        throw new WrongHttpMethodException($httpMethod);
      }
    } catch (ReflectionException $e) {
      throw new NotImplementedException;
    }
  }

  protected function findUserOrThrow(string $id) {
    if ($id === 'me') {
      // if (!$this->user->isLoggedIn()) {
      //   throw new ForbiddenRequestException; 
      // }

      // $id = $this->user->id;
      $id = '1fe2255e-50e2-11e6-beb8-9e71128cae77'; // @todo change the hardcoded ID for 'me'!
    }

    $user = $this->users->get($id);
    if (!$user) {
      throw new NotFoundException;
    }

    return $user;
  }

  protected function sendSuccessResponse($payload, $code = IResponse::S200_OK) {
    $this->sendJson([
      'success' => TRUE,
      'code' => $code,
      'payload' => $payload
    ]);
  }

}
