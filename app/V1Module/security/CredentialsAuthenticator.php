<?php

namespace App\Security;

use App\Exceptions\FrontendErrorMappings;
use App\Exceptions\WrongCredentialsException;
use App\Model\Entity\User;
use App\Model\Repository\Logins;
use Nette;

class CredentialsAuthenticator
{
    use Nette\SmartObject;

    /** @var Logins */
    private $logins;

    public function __construct(Logins $logins)
    {
        $this->logins = $logins;
    }

    /**
     * @param string $username
     * @param string $password
     * @return User
     * @throws WrongCredentialsException
     */
    function authenticate(string $username, string $password)
    {
        $user = $this->logins->getUser($username, $password);

        if ($user === null) {
            throw new WrongCredentialsException(
                "The username or password is incorrect.",
                FrontendErrorMappings::E400_101__WRONG_CREDENTIALS_LOCAL
            );
        }

        return $user;
    }
}
