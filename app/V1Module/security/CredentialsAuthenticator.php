<?php

namespace App\Security;

use App\Exceptions\FrontendErrorMappings;
use App\Exceptions\WrongCredentialsException;
use App\Model\Entity\User;
use App\Model\Repository\Logins;
use Nette;
use Nette\Security\Passwords;

class CredentialsAuthenticator
{
    use Nette\SmartObject;

    /** @var Logins */
    private $logins;

    /** @var Passwords */
    private $passwordsService;

    public function __construct(Logins $logins, Passwords $passwordsService)
    {
        $this->logins = $logins;
        $this->passwordsService = $passwordsService;
    }

    /**
     * @param string $username
     * @param string $password
     * @return User
     * @throws WrongCredentialsException
     */
    public function authenticate(string $username, string $password)
    {
        $user = $this->logins->getUser($username, $password, $this->passwordsService);

        if ($user === null) {
            throw new WrongCredentialsException(
                "The username or password is incorrect.",
                FrontendErrorMappings::E400_101__WRONG_CREDENTIALS_LOCAL
            );
        }

        return $user;
    }
}
