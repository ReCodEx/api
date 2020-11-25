<?php

namespace App\Security;

use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\InvalidAccessTokenException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\UnauthorizedException;
use App\Model\Entity\User;
use Nette\Security\IIdentity;
use Nette\Security\IUserStorage;
use Nette;

class UserStorage implements IUserStorage
{
    use Nette\SmartObject;

    const AUTH_HEADER = "Authorization";

    /** @var Nette\Http\IRequest */
    private $httpRequest;

    /** @var AccessManager */
    private $accessManager;

    private $authenticated;

    /** @var IIdentity */
    private $cachedIdentity;

    public function __construct(AccessManager $accessManager, Nette\Http\IRequest $httpRequest)
    {
        $this->httpRequest = $httpRequest;
        $this->accessManager = $accessManager;
    }

    /**
     * @param bool $state
     * @return self
     */
    function setAuthenticated($state)
    {
        $this->authenticated = $state;
        return $this;
    }

    /**
     * Is this user authenticated?
     * @return bool
     */
    function isAuthenticated()
    {
        return $this->httpRequest->getHeader(self::AUTH_HEADER) !== null || $this->authenticated;
    }

    /**
     * Set user identity (after login or for testing purposes)
     * @param IIdentity|null $identity
     * @return self
     * @throws InvalidArgumentException
     */
    function setIdentity(IIdentity $identity = null)
    {
        if ($identity !== null && !($identity instanceof Identity)) {
            throw new InvalidArgumentException("Wrong identity class");
        }

        $this->cachedIdentity = $identity;
        return $this;
    }

    /**
     * Returns current user identity, if any.
     * @return IIdentity|null
     */
    function getIdentity()
    {
        if ($this->cachedIdentity === null) {
            $token = $this->accessManager->getGivenAccessToken($this->httpRequest);

            if ($token === null) {
                $this->cachedIdentity = new Identity(null, null);
                return $this->cachedIdentity;
            }

            $decodedToken = $this->accessManager->decodeToken($token);
            $user = $this->accessManager->getUser($decodedToken);
            $this->checkTokenForRevocation($decodedToken, $user);
            $this->cachedIdentity = new Identity($user, $decodedToken);
        }

        return $this->cachedIdentity;
    }

    /**
     * @throws InvalidAccessTokenException
     * @throws InvalidArgumentException
     */
    protected function checkTokenForRevocation(AccessToken $token, User $user)
    {
        $validityThreshold = $user->getTokenValidityThreshold();

        $wasTokenIssuedAfterThreshold = $validityThreshold === null
            || $token->getIssuedAt() >= $validityThreshold->getTimestamp();

        if (!$wasTokenIssuedAfterThreshold) {
            throw new InvalidAccessTokenException("Your access token was revoked and cannot be used anymore");
        }
    }


    /**
     * Returns current user entity from user identity, if any.
     * @return ?\App\Model\Entity\User
     */
    function getUserData()
    {
        $identity = $this->getIdentity();
        if ($identity instanceof Identity) {
            return $identity->getUserData();
        } else {
            return null;
        }
    }

    /**
     * No-op - doesn't really make sense with this implementation
     * @param string|int|\DateTimeInterface $time number of seconds or timestamp
     * @param int $flags Log out when the browser is closed | Clear the identity from persistent storage?
     * @return self
     */
    function setExpiration($time, $flags = 0)
    {
        return $this;
    }

    /**
     * Why was user logged out? Who cares anyway...
     * @return int
     */
    function getLogoutReason()
    {
        return 0;
    }
}
