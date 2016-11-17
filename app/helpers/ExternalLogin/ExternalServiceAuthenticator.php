<?php

namespace App\Helpers\ExternalLogin;

use App\Exceptions\BadRequestException;
use Nette\Security\User;


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
   * Constructor with instantiation of all login services
   * @param CAS $cas Charles University autentication service
   */
  public function __construct(CAS $cas) {
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

  public function authenticate(string $serviceId, string $username, string $password) {
    $service = $this->getById($serviceId);
    return $service->getUser($username, $password);
  }
}
