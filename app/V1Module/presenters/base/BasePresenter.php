<?php

namespace App\V1Module\Presenters;

use App\Model\Repository\Users;
use App\Exception\NotFoundException;
use App\Exception\BadRequestException;
use App\Exception\ForbiddenRequestException;
use App\Exception\WrongHttpMethodException;

use Nette\Application\Application;
use Nette\Reflection;

class BasePresenter extends \App\Presenters\BasePresenter {
  
  /** @var Users */
  protected $users;
  
  /** @inject @var Application */
  public $application;

  /**
   * @param Users $users  Users repository
   */
  public function __construct(Users $users) {
    $this->users = $users;
  }
  
  public function startup() {
    parent::startup();
    $this->application->errorPresenter = "V1:ApiError";

    // checks, if the action has allowed method annotation
    $reflection = new Reflection\ClassType(get_class($this));
    $actionMethodName = $this->formatActionMethod($this->getAction());
    $httpMethod = $this->getHttpRequest()->getMethod();
    if ($reflection->getMethod($actionMethodName)->hasAnnotation($httpMethod) === FALSE) {
      throw new WrongHttpMethodException($httpMethod);
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

}
