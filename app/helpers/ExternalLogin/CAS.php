<?php

namespace App\Helpers\ExternalLogin;

use App\Model\Entity\User;
use Nette\Utils\Arrays;
use App\Helpers\LdapUserUtils;
use Toyota\Component\Ldap\Core\Node;
use Toyota\Component\Ldap\Core\NodeAttribute;

class CAS implements IExternalLoginService {

  /** @var string */
  private $serviceId;

  public function getServiceId(): string { return $this->serviceId; }

  /** @var LdapUserUtils */
  private $ldap;

  /** @var string */
  private $emailField;

  /** @var string */
  private $firstNameField;

  /** @var string */
  private $lastNameField;

  public function __construct(string $serviceId, array $ldapConnection, array $fields) {
    $this->serviceId = $serviceId;
    $this->ldap = new LdapUserUtils($ldapConnection);

    // The field names of user's information stored in the CAS LDAP
    $this->emailField = Arrays::get($fields, "email", "mail");
    $this->firstNameField = Arrays::get($fields, "firstName", "firstName");
    $this->lastNameField = Arrays::get($fields, "lastName", "firstName");
  }

  public function getUser(string $ukco, string $password): UserData {
    $data = $this->ldap->getUser($ukco, $password);
    $email = $this->getValue($data->get($this->emailField));
    $firstName = $this->getValue($data->get($this->firstNameField));
    $lastName = $this->getValue($data->get($this->lastNameField));

    // @todo remove with the real stuff
    // $email = "simon@rozsival.com";
    // $firstName = "Simon";
    // $lastName = "Rozsival";
    
    return new UserData($ukco, $email, $firstName, $lastName, $this);
  }

  private function getValue(NodeAttribute $attribute) {
    if ($attribute === null || $attribute->count() === 0) {
      throw new \Exception; // @todo Throw a specific information
    }

    return $attribute->current();
  }

}
