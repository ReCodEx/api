<?php
namespace App\Security;
use App\Exceptions\InvalidArgumentException;
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
   * @param  bool
   * @return void
   */
  function setAuthenticated($state)
  {
    $this->authenticated = $state;
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
   * @param IIdentity $identity
   * @throws InvalidArgumentException
   */
  function setIdentity(IIdentity $identity = null)
  {
    if ($identity !== null && !($identity instanceof Identity)) {
      throw new InvalidArgumentException("Wrong identity class");
    }

    $this->cachedIdentity = $identity;
  }

  /**
   * Returns current user identity, if any.
   * @return Identity
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
      $this->cachedIdentity = new Identity($user, $decodedToken);
    }

    return $this->cachedIdentity;
  }

  /**
   * Returns current user entity from user identity, if any.
   * @return ?\App\Model\Entity\User
   */
  function getUserData()
  {
    $identity = $this->getIdentity();
    if ($identity && $identity instanceof Identity) {
      return $identity->getUserData();
    } else {
      return null;
    }
  }

  /**
   * No-op - doesn't really make sense with this implementation
   * @param  string|int|\DateTimeInterface $time number of seconds or timestamp
   * @param  int $flags Log out when the browser is closed | Clear the identity from persistent storage?
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
