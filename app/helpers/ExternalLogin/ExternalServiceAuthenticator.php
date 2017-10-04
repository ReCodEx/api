<?php

namespace App\Helpers\ExternalLogin;

use App\Exceptions\BadRequestException;
use App\Exceptions\InvalidStateException;
use App\Exceptions\WrongCredentialsException;
use App\Model\Entity\User;
use App\Model\Repository\ExternalLogins;
use App\Model\Repository\Users;


/**
 * Mapper of service identification to object instance
 */
class ExternalServiceAuthenticator {

  /**
   * External authentication services
   * @var IExternalLoginService[]
   */
  private $services;

  /**
   * @var ExternalLogins
   */
  private $externalLogins;

  /**
   * @var Users
   */
  private $users;

  /**
   * Constructor with instantiation of all login services
   * @param ExternalLogins $externalLogins
   * @param Users $users
   * @param array $services
   */
  public function __construct(ExternalLogins $externalLogins, Users $users, ...$services) {
    $this->externalLogins = $externalLogins;
    $this->users = $users;
    $this->services = $services;
  }

  /**
   * Get external service depending on the ID
   * @param string $serviceId Identifier of wanted service
   * @param string|null $type Type of authentication process
   * @return IExternalLoginService Instance of login service with given ID
   * @throws BadRequestException when such service is not known
   */
  public function findService(string $serviceId, ?string $type = "default"): IExternalLoginService {
    foreach ($this->services as $service) {
      if ($service->getServiceId() === $serviceId && $service->getType() === $type) {
        return $service;
      }
    }

    throw new BadRequestException("Authentication service '$serviceId/$type' is not supported.");
  }

  /**
   * Authenticate a user against given external authentication service
   * @param IExternalLoginService $service
   * @param array $credentials
   * @return User
   * @throws WrongCredentialsException
   */
  public function authenticate(IExternalLoginService $service, ...$credentials) {
    $user = NULL;
    $userData = $service->getUser(...$credentials);
    try {
      $user = $this->findUser($service, $userData);
    } catch (WrongCredentialsException $e) {
      // second try - is there a user with a verified email corresponding to this one?
      if ($userData !== NULL) {
        $user = $this->tryConnect($service, $userData);
      }

      if ($user === NULL) {
        throw $e;
      }
    }

    return $user;
  }

  /**
   * Try to find a user account based on the data collected from
   * an external login service.
   * @param IExternalLoginService $service
   * @param UserData|null $userData
   * @return User
   * @throws WrongCredentialsException
   */
  private function findUser(IExternalLoginService $service, ?UserData $userData): User {
    if ($userData === NULL) {
      throw new WrongCredentialsException("Authentication failed.");
    }

    $user = $this->externalLogins->getUser($service->getServiceId(), $userData->getId());
    if ($user === NULL) {
      throw new WrongCredentialsException("Cannot authenticate this user through {$service->getServiceId()}.");
    }

    return $user;
  }


  /**
   * Try connecting given LDAP user to local ReCodEx user account.
   * @param IExternalLoginService $service
   * @param UserData $userData
   * @return User|null
   * @throws InvalidStateException
   */
  private function tryConnect(IExternalLoginService $service, UserData $userData): ?User {
    $unconnectedUsers = [];
    foreach ($userData->getEmails() as $email) {
      if (empty($email)) {
        continue;
      }

      $user = $this->users->getByEmail($email);
      if ($user && $user->isVerified()) {
        $unconnectedUsers[] = $user;
      }
    }

    if (count($unconnectedUsers) === 0) {
      // no recodex users are suitable for connecting to CAS account
      return null;
    } else if (count($unconnectedUsers) > 1) {
      // multiple recodex accounts were found for emails in CAS
      throw new InvalidStateException(
        "LDAP user '{$userData->getId()}' has multiple specified emails which are also registered in locally ReCodEx");
    }

    // there was only one suitable user, try to connect it
    $unconnectedUser = current($unconnectedUsers);
    if ($this->externalLogins->connect($service, $unconnectedUser, $userData->getId())) {
      return $unconnectedUser;
    }

    // user was not connected... normally unreachable
    return null;
  }

}
