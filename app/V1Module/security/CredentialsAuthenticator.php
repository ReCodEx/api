<?php

namespace App\Security;

use App\Exceptions\FrontendErrorMappings;
use App\Exceptions\WrongCredentialsException;
use App\Exceptions\ForbiddenRequestException;
use App\Model\Entity\User;
use App\Model\Repository\Logins;
use Nette;
use Nette\Security\Passwords;
use Nette\Http\IResponse;

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
        } elseif (!$user->isAllowed()) {
            throw new ForbiddenRequestException(
                "Forbidden Request - User account was disabled",
                IResponse::S403_Forbidden,
                FrontendErrorMappings::E403_002__USER_NOT_ALLOWED
            );
        }


        return $user;
    }
}
