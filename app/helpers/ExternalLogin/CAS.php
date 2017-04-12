<?php

namespace App\Helpers\ExternalLogin;

use App\Model\Entity\User;
use App\Helpers\LdapUserUtils;
use App\Exceptions\WrongCredentialsException;
use App\Exceptions\CASMissingInfoException;

use Nette\InvalidArgumentException;
use Nette\Utils\Arrays;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Nette\Utils\Validators;

use Toyota\Component\Ldap\Core\Node;
use Toyota\Component\Ldap\Core\NodeAttribute;

use GuzzleHttp\Client;

/**
 * Login provider of Charles University, CAS - Centrální autentizační služba UK.
 * CAS is basically just LDAP database, but it has some specifics. We are not
 * allowed to have account with sufficient permissions to read password hashes
 * for other users like normal LDAP login services works. Instead, we have only
 * anonymous binding which don't allow us to read almost anything. So, user must
 * login by his UKCO (unique student/staff university number) and we bind into
 * LDAP by his credentials. If this succeeded, we know the credentials was correct
 * and we can read his email address and other information to save them into our
 * database. There is also function to find user by email, but it's not guaranteed
 * to be unique, so this method may fail (but very unlikely).
 */
class CAS implements IExternalLoginService {

  /** @var string Unique identifier of this login service, for example "cas-uk" */
  private $serviceId;

  /**
   * Gets identifier for this service
   * @return string Login service unique identifier
   */
  public function getServiceId(): string { return $this->serviceId; }

  /** @var LdapUserUtils Ldap utilities for bindings and searching */
  private $ldap;

  /** @var string Name of LDAP field containing user mail address */
  private $emailField;

  /** @var string Name of LDAP field containing user first name */
  private $firstNameField;

  /** @var string Name of LDAP field containing user last name */
  private $lastNameField;

  /**
   * Constructor
   * @param string $serviceId      Identifier of this login service, must be unique
   * @param array  $ldapConnection Parameters for instantiating LdapUserUtils class
   * @param array  $fields         Name of LDAP fields containing requested metadata (email, first and last name)
   */
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
   * @param  string $email Email address of user, whose UKCO is requested
   * @return string|NULL
   */
  public function getUKCO(string $email) {
    return $this->ldap->findUserByMail($email, $this->emailField);
  }

  /**
   * Read user's data from the CAS UK, if the credentials provided by the user are correct.
   * @param  string $username Email or identification number of the person
   * @param  string $password User's password
   * @return UserData Information known about this user
   * @throws WrongCredentialsException when login is not successfull
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
    $email = $this->getValue($data->get($this->emailField)); // throws when field is invalid or empty
    $firstName = $this->getValue($data->get($this->firstNameField)); // throws when field is invalid or empty
    $lastName = $this->getValue($data->get($this->lastNameField)); // throws when field is invalid or empty
    $degreesBeforeName = ""; // @todo
    $degreesAfterName = ""; // @todo

    return new UserData($ukco, $email, $firstName, $lastName, $degreesBeforeName, $degreesAfterName, $this);
  }

    /**
     * Read user's data from the identity provider, if the ticket provided by the user is valid
     * @param  string $ticket
     * @param  string $service
     * @return UserData Information known about this user
     * @throws WrongCredentialsException
     */
  function getUserWithTicket(string $ticket, string $service = "https://recodex.projekty.ms.mff.cuni.cz"): UserData {
    $client = new Client([ "base_uri" => "https://idp.cuni.cz/cas/" ]);
    $service = urlencode($service);
    $ticket = urlencode($ticket);
    $url = "serviceValidate?service=$service&ticket=$ticket&format=json";
    $res = $client->get($url);

    if ($res->getStatusCode() === 200) { // the response should be 200 even if the ticket is invalid
      try {
        $data = Json::decode($res->getBody());
      } catch (JsonException $e) {
        throw new WrongCredentialsException("The ticket '$ticket' cannot be validated as the response from the server is corrupted or incomplete.");
      }

      try {
        $info = Arrays::get($data, ["successResponse", "authenticationSuccess", "attributes"]);
        $ukco = Arrays::get($info, "cunipersonalid");
        $email = Arrays::get($info, "mail");
        $firstName = Arrays::get($info, "givenname");
        $lastName = Arrays::get($info, "sn");
        $degreesBeforeName = ""; // @todo
        $degreesAfterName = ""; // @todo

        return new UserData($ukco, $email, $firstName, $lastName, $degreesBeforeName, $degreesAfterName, $this);
      } catch (InvalidArgumentException $e) {
        throw new WrongCredentialsException("The ticket '$ticket' is not valid and does not belong to a CUNI student or staff or it was already used.");
      }
    } else {
      throw new WrongCredentialsException("The ticket '$ticket' cannot be validated as the CUNI CAS service is unavailable.");
    }
  }

  /**
   * Get value of an LDAP attribute.
   * @param  NodeAttribute $attribute The attribute
   * @return mixed                    The value
   * @throws CASMissingInfoException  If attribute is invalid (empty or NULL)
   */
  private function getValue(NodeAttribute $attribute) {
    if ($attribute === null || $attribute->count() === 0) {
      throw new CASMissingInfoException();
    }

    return $attribute->current();
  }

}
