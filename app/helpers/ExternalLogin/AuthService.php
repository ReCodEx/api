<?php

namespace App\Helpers\ExternalLogin;

use App\Exceptions\BadRequestException;


/**
 * Mapper of service identification to object instance
 */
class AuthService {

  /**
   * @var CAS
   * @inject
   */
  public $CAS;

  /**
   * Get external service depending on the ID
   * @param string $serviceId Identifier of wanted service
   * @return IExternalLoginService Instance of login service with given ID
   * @throws BadRequestException when such service is not known
   */
  public function getById(string $serviceId): IExternalLoginService {
    switch (strtolower($serviceId)) {
      case $this->CAS->getServiceId():
        return $this->CAS;
      default:
        throw new BadRequestException("Authentication service '$serviceId' is not supported.");
    }
  }
}
