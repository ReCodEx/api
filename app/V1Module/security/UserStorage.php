<?php

namespace App\Security;

use App\Exceptions\InvalidAccessTokenException;
use App\Exceptions\InvalidArgumentException;
use App\Model\Entity\User;
use Nette;
use Nette\Security\IIdentity;

class UserStorage implements Nette\Security\UserStorage
{
    use Nette\SmartObject;

    private const AUTH_HEADER = "Authorization";

    /** @var Nette\Http\IRequest */
    private $httpRequest;

    /** @var AccessManager */
    private $accessManager;

    private $authenticated;

    /** @var ?IIdentity */
    private $cachedIdentity;

    public function __construct(AccessManager $accessManager, Nette\Http\IRequest $httpRequest)
    {
        $this->httpRequest = $httpRequest;
        $this->accessManager = $accessManager;
    }

    /**
     * @inheritDoc
     */
    public function setExpiration(?string $expire, bool $clearIdentity): void
    {
        // no-op - doesn't really make sense with this implementation
    }

    /**
     * @inheritDoc
     */
    public function saveAuthentication(IIdentity $identity): void
    {
        if ($identity !== null && !($identity instanceof Identity)) {
            throw new InvalidArgumentException("Wrong identity class");
        }

        $this->authenticated = true;
        $this->cachedIdentity = $identity;
    }

    /**
     * @inheritDoc
     */
    public function clearAuthentication(bool $clearIdentity): void
    {
        $this->authenticated = false;
        if ($clearIdentity) {
            $this->cachedIdentity = null;
        }
    }

    /**
     * @inheritDoc
     */
    public function getState(): array
    {
        return [$this->isAuthenticated(), $this->getIdentity(), 0];
    }

    /**
     * Is this user authenticated?
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return $this->httpRequest->getHeader(self::AUTH_HEADER) !== null || $this->authenticated;
    }

    /**
     * Returns current user identity, if any.
     * @return IIdentity|null
     */
    public function getIdentity(): ?IIdentity
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
     * Returns current user entity from user identity, if any.
     * @return ?\App\Model\Entity\User
     */
    public function getUserData()
    {
        $identity = $this->getIdentity();
        if ($identity instanceof Identity) {
            return $identity->getUserData();
        } else {
            return null;
        }
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
}
