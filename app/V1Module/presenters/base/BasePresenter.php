<?php

namespace App\V1Module\Presenters;

use ReflectionException;
use App\Exceptions\NotFoundException;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\WrongHttpMethodException;
use App\Exceptions\NotImplementedException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\InternalServerErrorException;

use App\Security\AccessManager;
use App\Security\Authorizator;
use App\Model\Repository\Users;
use App\Helpers\Validators;
//use Nette\Utils\Validators;

use Nette\Security\Identity;
use Nette\Application\Application;
use Nette\Application\Responses;
use Nette\Http\IResponse;
use Nette\Reflection;
use Nette\Utils\Arrays;

class BasePresenter extends \App\Presenters\BasePresenter {
  
  /**
   * @var Users
   * @inject
   */
  public $users;

  /**
   * @var AccessManager
   * @inject
   */
  public $accessManager;

  /**
   * @var Authorizator
   * @inject
   */
  public $authorizator;
 
  /**
   * @var Application
   * @inject
   */
  public $application;

  /** @var object Processed parameters from annotations */
  protected $parameters;

  public function startup() {
    parent::startup();
    $this->application->errorPresenter = "V1:ApiError";
    $this->tryLogin();
    $this->parameters = new \stdClass;

    try {
      $presenterReflection = new Reflection\ClassType(get_class($this));
      $actionMethodName = $this->formatActionMethod($this->getAction());
      $actionReflection = $presenterReflection->getMethod($actionMethodName);
    } catch (ReflectionException $e) {
      throw new NotImplementedException;
    }

    Validators::init();
    $this->processParams($actionReflection);
    $this->restrictUnauthorizedAccess($presenterReflection);
    $this->restrictUnauthorizedAccess($actionReflection);
  }

  /**
   * Try to authenticate the user from the parameters given in the request.
   * @return void
   */
  private function tryLogin() {
    // try to authenticate the user
    $identity = $this->accessManager->getIdentity($this->getHttpRequest());
    if ($identity !== NULL) {
      $this->user->login($identity);
      $this->user->setAuthorizator($this->authorizator);
      $this->authorizator->setScopes($this->user, $identity->scopes);
    }
  }

  /**
   * Is current user in the given scope?
   * @param string $scope Scope ID
   * @return bool
   */
  protected function isInScope(string $scope): bool {
    return $this->user->isLoggedIn() && $this->authorizator->isInScope($this->user, $scope);
  }

  private function processParams(\Reflector $reflection) {
    $annotations = $reflection->getAnnotations();
    $requiredFields = Arrays::get($annotations, "Param", []);

    foreach ($requiredFields as $field) {
      $type = strtolower($field->type);
      $name = $field->name;
      $validationRule = isset($field->validation) ? $field->validation : NULL;
      $msg = isset($field->msg) ? $field->msg : NULL;
      $required = isset($field->required) ? $field->required : TRUE;

      $value = NULL;
      switch ($type) {
        case "post":
          $value = $this->getPostField($name, $required);
          break;
        case "query":
          $value = $this->getQueryField($name, $required);
        
        default:
          throw new InternalServerErrorException("Unknown parameter type '$type'");
      }

      if ($validationRule !== NULL && $value !== NULL) {
        $value = $this->validateValue($name, $value, $validationRule, $msg);
      }

      $this->parameters->$name = $value;
    }
  }

  private $post = NULL;
  private function getPostField($param, $required = TRUE) {
    
    if ($this->post === NULL) {
      $req = $this->getHttpRequest();
      if ($req->isMethod("POST")) {
        $this->post = $req->post;
      } else if ($req->isMethod("PUT") || $req->isMethod("DELETE")) {
        parse_str(file_get_contents('php://input'), $this->post);
      } else {
        throw new WrongHttpMethodException("Cannot get the post parameters in method '" . $req->getMethod() . "'.");
      }
    }

    if (isset($this->post[$param])) {
      return $this->post[$param];
    } else if ($required) {
      throw new BadRequestException("Missing required POST field $param");
    } else {
      return NULL;
    }
  }

  private function getQueryField($param, $required = TRUE) {
    $value = $this->getHttpRequest()->getQuery($param);
    if ($value === NULL && $required) { 
      throw new BadRequestException("Missing required query field $param");
    }
    return $value;
  }

  private function validateValue($param, $value, $validationRule, $msg = NULL) {
    $value = Validators::preprocessValue($value, $validationRule);
    if (Validators::is($value, $validationRule) === FALSE) {
      throw new InvalidArgumentException(
        $param,
        $msg !== NULL ? $msg : "The value '$value' does not match validation rule '$validationRule' - for more information check the documentation of Nette\\Utils\\Validators"
      );
    }

    return $value;
  }

  /**
   * Restricts access to certain actions according to ACL
   * @param   \Reflector         $reflection Information about current action
   * @throws  ForbiddenRequestException
   */
  private function restrictUnauthorizedAccess(\Reflector $reflection) {
    if ($reflection->hasAnnotation("LoggedIn") && !$this->user->isLoggedIn()) {
      throw new UnauthorizedException;
    }
        
    if ($reflection->hasAnnotation("Role")
      && !$this->user->isInRole($reflection->getAnnotation("Role"))) {
        throw new ForbiddenRequestException("You do not have sufficient rights to perform this action.");
    }

    if ($reflection->hasAnnotation("UserIsAllowed")) {
      foreach ($reflection->getAnnotation("UserIsAllowed") as $resource => $action) {
        if ($this->user->isAllowed($resource, $action) === FALSE) {
          throw new ForbiddenRequestException("You are not allowed to perform this action.");
        }
      }
    }
  }

  protected function sendSuccessResponse($payload, $code = IResponse::S200_OK) {
    $resp = $this->getHttpResponse();
    $resp->setCode($code);
    $this->sendJson([
      "success" => TRUE,
      "code" => $code,
      "payload" => $payload
    ]);
  }
}
