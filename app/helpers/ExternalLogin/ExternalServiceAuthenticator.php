<?php

namespace App\Helpers\ExternalLogin;

use App\Exceptions\BadRequestException;
use App\Model\Entity\User;
use App\Model\Repository\ExternalLogins;


/**
 * Mapper of service identification to object instance
 */
class ExternalServiceAuthenticator {

  /**
   * Auth service of Charles University
   * @var CAS
   */
  private $cas;

  /**
   * @var ExternalLogins
   */
  private $externalLogins;

  /**
   * Constructor with instantiation of all login services
   * @param CAS $cas Charles University autentication service
   */
  public function __construct(ExternalLogins $externalLogins, CAS $cas) {
    $this->externalLogins = $externalLogins;
    $this->cas = $cas;
  }

  /**
   * Get external service depending on the ID
   * @param string $serviceId Identifier of wanted service
   * @return IExternalLoginService Instance of login service with given ID
   * @throws BadRequestException when such service is not known
   */
  public function getById(string $serviceId): IExternalLoginService {
    switch (strtolower($serviceId)) {
      case $this->cas->getServiceId():
        return $this->cas;
      default:
        throw new BadRequestException("Authentication service '$serviceId' is not supported.");
    }
  }

  /**
   * Authenticate a user against given external authentication service
   * @param string $serviceId
   * @param string $username
   * @param string $password
   * @return User|NULL
   */
  public function authenticate(string $serviceId, string $username, string $password) {
    $service = $this->getById($serviceId);
    $userData = $service->getUser($username, $password);
    return $this->externalLogins->getUser($serviceId, $userData->getId());
  }

  /**
   * Authenticate a user against given external authentication service
   * @param string $serviceId
   * @param string $ticket Some kind of a temporary ticket/token which should be used for finding the user account
   * @return User|NULL
   */
  public function authenticateWithTicket(string $serviceId, string $ticket) {
    $service = $this->getById($serviceId);
    $userData = $service->getUserWithTicket($ticket);
    return $this->externalLogins->getUser($serviceId, $userData->getId());
  }
}
