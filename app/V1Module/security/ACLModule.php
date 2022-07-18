<?php

namespace App\Security;

abstract class ACLModule
{
    /** @var UserStorage */
    private $userStorage;

    /** @var ?Identity */
    private $identity;

    /** @var IAuthorizator */
    private $authorizator;

    public function __construct(UserStorage $userStorage, IAuthorizator $authorizator, ?Identity $identity = null)
    {
        $this->userStorage = $userStorage;
        $this->authorizator = $authorizator;
        $this->identity = $identity;
    }

    abstract protected function getResourceName();

    protected function check($action, $context): bool
    {
        return $this->authorizator->isAllowed(
            $this->identity ?? $this->userStorage->getIdentity(),
            $this->getResourceName(),
            $action,
            $context
        );
    }
}
