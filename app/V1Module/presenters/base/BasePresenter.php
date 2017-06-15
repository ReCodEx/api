<?php

namespace App\V1Module\Presenters;

use App\Model\Entity\User;
use App\Security\Identity;
use App\Security\Resource;
use Exception;
use LogicException;
use Nette\Reflection\ClassType;
use ReflectionException;
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
use App\Model\Repository\UserActions;
use App\Helpers\Validators;
//use Nette\Utils\Validators;

use Nette\Application\Application;
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
   * @var UserActions
   * @inject
   */
  public $userActions;

  /**
   * @var AccessManager
   * @inject
   */
  public $accessManager;

  /**
   * @var Application
   * @inject
   */
  public $application;

  /**
   * @var Authorizator
   * @inject
   */
  public $authorizator;

  /** @var object Processed parameters from annotations */
  protected $parameters;

  public function startup() {
    parent::startup();
    $this->application->errorPresenter = "V1:ApiError";
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
  }

  /**
   * @return User
   * @throws ForbiddenRequestException
   */
  protected function getCurrentUser(): User {
    /** @var Identity $identity */
    $identity = $this->getUser()->getIdentity();

    if ($identity === NULL) {
      throw new ForbiddenRequestException();
    }

    return $identity->getUserData();
  }

  /**
   * Is current user in the given scope?
   * @param string $scope Scope ID
   * @return bool
   */
  protected function isInScope(string $scope): bool {
    /** @var Identity $identity */
    $identity = $this->getUser()->getIdentity();

    if (!$identity) {
      return FALSE;
    }

    return $identity->isInScope($scope);
  }

  private function processParams(Reflection\Method $reflection) {
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
          break;
        default:
          throw new InternalServerErrorException("Unknown parameter type '$type'");
      }

      if ($validationRule !== NULL && $value !== NULL) {
        $value = $this->validateValue($name, $value, $validationRule, $msg);
      }

      $this->parameters->$name = $value;
    }
  }

  private function getPostField($param, $required = TRUE) {
    $req = $this->getRequest();
    $post = $req->getPost();

    if ($req->isMethod("POST")) {
      // nothing to see here...
    } else if ($req->isMethod("PUT") || $req->isMethod("DELETE")) {
      parse_str(file_get_contents('php://input'), $post);
    } else {
      throw new WrongHttpMethodException("Cannot get the post parameters in method '" . $req->getMethod() . "'.");
    }

    if (isset($post[$param])) {
      return $post[$param];
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

  protected function sendSuccessResponse($payload, $code = IResponse::S200_OK) {
    if ($this->getUser()->isLoggedIn()) {
      $params = $this->getRequest()->getParameters();
      unset($params[self::ACTION_KEY]);
      $this->userActions->log($this->getAction(TRUE), $params, $code);
    }

    $resp = $this->getHttpResponse();
    $resp->setCode($code);
    $this->sendJson([
      "success" => TRUE,
      "code" => $code,
      "payload" => $payload
    ]);
  }
}
