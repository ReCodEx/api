<?php

namespace App\Helpers\Mocks;

use Nette;
use Nette\Security\IIdentity;

class MockUserStorage implements Nette\Security\UserStorage
{
    public function setExpiration(?string $expire, bool $clearIdentity): void
    {
    }

    public function saveAuthentication(IIdentity $identity): void
    {
    }

    public function clearAuthentication(bool $clearIdentity): void
    {
    }

    public function getState(): array
    {
        return [false, null, 0];
    }
}
