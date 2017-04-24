<?php

namespace App\Helpers\ExternalLogin;

use App\Exceptions\BadRequestException;
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
   * @param array $services
   * @internal param CAS $cas Charles University autentication service
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
    $userData = $service->getUser(...$credentials);
    $user = NULL;

    try {
      $user = $this->findUser($service, $userData);
    } catch (WrongCredentialsException $e) {
      $user = $this->tryConnect($service, $userData);
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
   * @param IExternalLoginService $service
   * @param UserData $userData
   * @return User|bool|null
   */
  private function tryConnect(IExternalLoginService $service, UserData $userData): ?User {
    if (!empty($userData->getEmail())) {
      $unconnectedUser = $this->users->getByEmail($userData->getEmail());
      if ($unconnectedUser
        && $unconnectedUser->isVerified()
        && $this->externalLogins->connect($service, $unconnectedUser, $userData->getId())
      ) {
        return $unconnectedUser;
      }
    }

    return NULL;
  }
}
