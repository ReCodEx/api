<?php
namespace App\Security;


abstract class ACLModule {
  /** @var UserStorage */
  private $userStorage;

  /** @var IAuthorizator */
  private $authorizator;

  public function __construct(UserStorage $userStorage, IAuthorizator $authorizator) {
    $this->userStorage = $userStorage;
    $this->authorizator = $authorizator;
  }

  protected abstract function getResourceName();

  protected function check($action, $context): bool {
    return $this->authorizator->isAllowed(
      $this->userStorage->getIdentity(),
      $this->getResourceName(),
      $action,
      $context
    );
  }
}