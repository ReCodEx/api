<?php

namespace App\V1Module\Presenters;

use ReflectionException;
use App\Exception\NotFoundException;
use App\Exception\BadRequestException;
use App\Exception\ForbiddenRequestException;
use App\Exception\WrongHttpMethodException;
use App\Exception\NotImplementedException;

use App\Authentication\AccessManager;
use App\Model\Repository\Users;

use Nette\Application\Application;
use Nette\Http\IResponse;
use Nette\Reflection;

class BasePresenter extends \App\Presenters\BasePresenter {
  
  /** @inject @var Users */
  public $users;

  /** @inject @var AccessManager */
  public $accessManager;
 
  /** @inject @var Application */
  public $application;

  public function startup() {
    parent::startup();
    $this->application->errorPresenter = "V1:ApiError";

    // checks, if the action has allowed method annotation
    try {
      $presenterReflection = new Reflection\ClassType(get_class($this));
      $actionMethodName = $this->formatActionMethod($this->getAction());
      $actionReflection = $presenterReflection->getMethod($actionMethodName);
    } catch (ReflectionException $e) {
      throw new NotImplementedException;
    }

    $this->restrictHttpMethod($actionReflection);
    $this->restrictUnauthorizedAccess($presenterReflection);
    $this->restrictUnauthorizedAccess($actionReflection);
  }

  /**
   * Restricts access to certain actions for given HTTP methods using annotations
   * @param   \Reflector         $reflection Information about current action
   * @throws  WrongHttpMeethodException
   */
  protected function restrictHttpMethod(\Reflector $reflection) {
    $httpMethod = $this->getHttpRequest()->getMethod();
    if ($reflection->hasAnnotation(strtoupper($httpMethod)) === FALSE) {
      throw new WrongHttpMethodException($httpMethod);
    }
  }

  /**
   * Restricts access to certain actions for logged in users in certain roles
   * @param   \Reflector         $reflection Information about current action
   * @throws  ForbiddenRequestException
   */
  protected function restrictUnauthorizedAccess(\Reflector $reflection) {
    if ($reflection->hasAnnotation('LoggedIn')
        || $reflection->hasAnnotation('Role')) {
      $user = $this->accessManager->getUserFromRequestOrThrow($this->getHttpRequest());
      $this->user->login(new Identity($user->getId(), $user->getRole())); // @todo: replace the hard-coded roles

      if ($reflection->hasAnnotation('Role')
        && !$this->user->isInRole($reflection->getAnnotation('Role'))) {
          throw new ForbiddenRequestException('You do not have sufficient rights to perform this action.');
        }
    }
  }

  protected function findUserOrThrow(string $id) {
    if ($id === 'me') {
      if (!$this->user->isLoggedIn()) {
        throw new ForbiddenRequestException; 
      }

      $id = $this->user->id;
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
