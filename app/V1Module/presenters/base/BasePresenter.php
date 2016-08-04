<?php

namespace App\V1Module\Presenters;

use ReflectionException;
use App\Exception\NotFoundException;
use App\Exception\BadRequestException;
use App\Exception\ForbiddenRequestException;
use App\Exception\WrongHttpMethodException;
use App\Exception\NotImplementedException;
use App\Exception\InvalidArgumentException;
use App\Exception\InternalServerErrorException;

use App\Security\AccessManager;
use App\Security\Authorizator;
use App\Model\Repository\Users;

use Nette\Security\Identity;
use Nette\Application\Application;
use Nette\Http\IResponse;
use Nette\Reflection;
use Nette\Utils\Arrays;
use Nette\Utils\Validators;

class BasePresenter extends \App\Presenters\BasePresenter {
  
  /** @inject @var Users */
  public $users;

  /** @inject @var AccessManager */
  public $accessManager;

  /** @inject @var Authorizator */
  public $authorizator;
 
  /** @inject @var Application */
  public $application;

  public function startup() {
    parent::startup();
    $this->application->errorPresenter = "V1:ApiError";
    $this->tryLogin();

    try {
      $presenterReflection = new Reflection\ClassType(get_class($this));
      $actionMethodName = $this->formatActionMethod($this->getAction());
      $actionReflection = $presenterReflection->getMethod($actionMethodName);
    } catch (ReflectionException $e) {
      throw new NotImplementedException;
    }

    $this->validateRequiredParams($actionReflection);
    $this->restrictUnauthorizedAccess($presenterReflection);
    $this->restrictUnauthorizedAccess($actionReflection);

    // now we can restrict HTTP methods other than OPTIONS
    $this->restrictHttpMethod($actionReflection);
  }

  private function tryLogin() {
    try {
      $user = $this->accessManager->getUserFromRequestOrThrow($this->getHttpRequest());
      $this->user->login(new Identity($user->getId(), $user->getRole()->id, $user->jsonSerialize()));
      $this->user->setAuthorizator($this->authorizator);
    } catch (\Exception $e) {
      // silent error
    }
  }

  /**
   * Restricts access to certain actions for given HTTP methods using annotations
   * @param   \Reflector         $reflection Information about current action
   * @throws  WrongHttpMeethodException
   */
  private function restrictHttpMethod(\Reflector $reflection) {
    $httpMethod = $this->getHttpRequest()->getMethod();
    if ($reflection->hasAnnotation(strtoupper($httpMethod)) === FALSE) {
      throw new WrongHttpMethodException($httpMethod);
    }
  }

  private function validateRequiredParams(\Reflector $reflection) {
    $annotations = $reflection->getAnnotations();
    $requiredFields = Arrays::get($annotations, 'RequiredField', []);

    foreach ($requiredFields as $field) {
      $type = strtolower($field->type);
      $name = $field->name;
      $validationRule = isset($field->validation) ? $field->validation : NULL;

      switch ($type) {
        case 'post':
          $this->validatePostField($name, $validationRule);
          break;
        case 'query':
          $this->validateQueryField($name, $validationRule);
        
        default:
          throw new InternalServerErrorException("Unknown parameter type '$type'");
      }
    }
  }

  private function validatePostField($param, $validationRule = NULL) {
    $value = $this->getHttpRequest()->getPost($param);
    if ($value === NULL) { 
      throw new BadRequestException("Missing required POST field $param");
    }

    if ($validationRule !== NULL) {
      $this->validateValue($param, $value, $validationRule);
    }
  }

  private function validateQueryField($param, $validationRule = NULL) {
    $value = $this->getHttpRequest()->getQuery($param);
    if ($value === NULL) { 
      throw new BadRequestException("Missing required query field $param");
    }

    if ($validationRule !== NULL) {
      $this->validateValue($value, $validationRule);
    }
  }

  private function validateValue($param, $value, $validationRule) {
    if (Validators::is($value, $validationRule) === FALSE) {
      throw new InvalidArgumentException($param, "The value '$value' does not match validation rule '$validationRule' - for more information check the documentation of Nette\\Utils\\Validators");
    }
  }

  /**
   * Restricts access to certain actions according to ACL
   * @param   \Reflector         $reflection Information about current action
   * @throws  ForbiddenRequestException
   */
  private function restrictUnauthorizedAccess(\Reflector $reflection) {
    if ($reflection->hasAnnotation('LoggedIn') && !$this->user->isLoggedIn()) {
      throw new ForbiddenRequestException('You must be logged in - you probably didn\'t provide a valid access token in the HTTP request.');
    }
        
    if ($reflection->hasAnnotation('Role')
      && !$this->user->isInRole($reflection->getAnnotation('Role'))) {
        throw new ForbiddenRequestException('You do not have sufficient rights to perform this action.');
    }

    if ($reflection->hasAnnotation('UserIsAllowed')) {
      foreach ($reflection->getAnnotation('UserIsAllowed') as $resource => $action) {
        if ($this->user->isAllowed($resource, $action) === FALSE) {
          throw new ForbiddenRequestException('You are not allowed to perform this action.');
        }
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
    // @todo set HTTP response code
    $this->sendJson([
      'success' => TRUE,
      'code' => $code,
      'payload' => $payload
    ]);
  }

}
