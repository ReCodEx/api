<?php

namespace App\Helpers\ExternalLogin;

use App\Model\Entity\User;
use App\Helpers\LdapUserUtils;
use App\Exceptions\WrongCredentialsException;

use Nette\Utils\Arrays;
use Nette\Utils\Validators;

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
    $this->lastNameField = Arrays::get($fields, "lastName", "lastName");
  }

  /**
   * Tries to find UKCO for the given email. The ID cannot be determined if there is no
   * person with this email or if there mare multiple people sharing the email.
   * @param  string $email [description]
   * @return string|NULL
   */
  public function getUKCO(string $email) {
    return $this->ldap->findUserByMail($email);
  }

  /**
   * Read user's data from the CAS UK, if the credentials provided by the user are correct.
   * @param  string $username Email or identification number of the person
   * @param  string $password User's password
   * @return UserData
   */
  public function getUser(string $username, string $password): UserData {
    $ukco = $username;
    if (Validators::isEmail($username)) {
      $ukco = $this->getUKCO($username);
      if ($ukco === NULL) {
        throw new WrongCredentialsException("Email address '$username' cannot be paired with a specific user in CAS.");
      }
    }

    $data = $this->ldap->getUser($ukco, $password); // throws when the credentials are wrong
    $email = $this->getValue($data->get($this->emailField));
    $firstName = $this->getValue($data->get($this->firstNameField));
    $lastName = $this->getValue($data->get($this->lastNameField));

    return new UserData($ukco, $email, $firstName, $lastName, $this);
  }

  /**
   * Get value of an attribute.
   * @param  NodeAttribute $attribute The attribute
   * @return mixed                    The value
   */
  private function getValue(NodeAttribute $attribute) {
    if ($attribute === null || $attribute->count() === 0) {
      throw new \Exception; // @todo Throw a specific information
    }

    return $attribute->current();
  }

}
