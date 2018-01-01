<?php

namespace App\Helpers\ExternalLogin\CAS;

use App\Exceptions\InvalidArgumentException as AppInvalidArgumentException;
use App\Helpers\ExternalLogin\IExternalLoginService;
use App\Helpers\ExternalLogin\UserData;
use App\Exceptions\WrongCredentialsException;
use App\Exceptions\CASMissingInfoException;

use App\Model\Entity\User;
use GuzzleHttp\Psr7\Request;
use Nette\InvalidArgumentException;
use Nette\Utils\Arrays;
use Nette\Utils\Json;
use Nette\Utils\JsonException;

use GuzzleHttp\Client;


/**
 * Login provider of Charles University, CAS - Centrální autentizační služba UK.
 * CAS is basically just LDAP database, but it has some specifics. Users can sign
 * into the system without revealing their passwords to us, we are just given a special
 * temporary token (a ticket) which is then validated against the CAS HTTP server
 * and if the ticket is valid then we receive the details about the person as we
 * would with direct access into the LDAP database.
 *
 * This is hard to test on a local server, as the CAS will only reveal the sensitive
 * personal information to computers in the CUNI network.
 */
class OAuthLoginService implements IExternalLoginService {

  /** @var string Unique identifier of this login service, for example "cas-uk" */
  private $serviceId;

  /**
   * Gets identifier for this service
   * @return string Login service unique identifier
   */
  public function getServiceId(): string { return $this->serviceId; }

  /**
   * @return string The OAuth authentication
   */
  public function getType(): string { return "oauth"; }

  /** @var string Name of JSON field containing user's UKCO */
  private $ukcoField;

  /** @var string Name of JSON field containing user mail address */
  private $emailField;

  /** @var string Name of JSON field containing user's affiliation with CUNI */
  private $affiliationField;

  /** @var string Name of JSON field containing user first name */
  private $firstNameField;

  /** @var string Name of JSON field containing user last name */
  private $lastNameField;

  /** @var array Array containing identifier which registers person as a supervisor in retrieved affiliation */
  private $supervisorAffiliations;

  /** @var string The base URI for the validation of login tickets */
  private $casHttpBaseUri;

  /**
   * Constructor
   * @param string $serviceId Identifier of this login service, must be unique
   * @param array $options
   * @param array $fields
   */
  public function __construct(string $serviceId, array $options, array $fields) {
    $this->serviceId = $serviceId;

    // The field names of user's information stored in the CAS LDAP
    $this->ukcoField = Arrays::get($fields, "ukco", "cunipersonalid");
    $this->affiliationField = Arrays::get($fields, "affiliation", "edupersonscopedaffiliation");
    $this->supervisorAffiliations = Arrays::get($fields, "supervisorAffiliations", []);
    $this->emailField = Arrays::get($fields, "email", "mail");
    $this->firstNameField = Arrays::get($fields, "firstName", "givenname");
    $this->lastNameField = Arrays::get($fields, "lastName", "sn");

    // The CAS HTTP validation endpoint
    $this->casHttpBaseUri = Arrays::get($options, "baseUri", "https://idp.cuni.cz/cas/");
  }

  /**
   * Read user's data from the identity provider, if the ticket provided by the user is valid
   * @param array $credentials
   * @return UserData Information known about this user
   * @throws AppInvalidArgumentException
   * @throws CASMissingInfoException
   */
  public function getUser($credentials): UserData {
    $ticket = Arrays::get($credentials, "ticket", NULL);
    $clientUrl = Arrays::get($credentials, "clientUrl", NULL);

    if ($ticket === NULL || $clientUrl === NULL) {
        throw new AppInvalidArgumentException("The ticket or the client URL is missing for validation of the request.");
    }

    $info = $this->validateTicket($ticket, $clientUrl);
    return $this->getUserData($ticket, $info);
  }

  /**
   * @param string $ticket
   * @param string $clientUrl
   * @return array
   * @throws WrongCredentialsException
   */
  private function validateTicket(string $ticket, string $clientUrl) {
    $client = new Client;
    $url = $this->getValidationUrl($ticket, $clientUrl);
    $req = new Request('GET', $url);
    $res = $client->send($req);
    $data = NULL;

    if ($res->getStatusCode() === 200) { // the response should be 200 even if the ticket is invalid
      try {
        $body = $res->getBody();

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        $err = libxml_get_errors();
        if ($err) {
          throw new WrongCredentialsException("The ticket '$ticket' cannot be validated as the response from the server is corrupted or incomplete.");
        }

        // If namespace selection is required ...
        $namespaces = $xml->getDocNamespaces();
        if ($namespaces) {
          $namespace = empty($namespaces['cas']) ? reset($namespaces) : $namespaces['cas'];
          $xml = simplexml_load_string($body, "SimpleXMLElement", 0, $namespace);
          $err = libxml_get_errors();
          if ($err) {
            throw new WrongCredentialsException("The ticket '$ticket' cannot be validated as the response from the server is corrupted or incomplete.");
          }
        }

        // A trick that utilizes JSON serialization of SimpleXML objects to convert the XML into an array.
        $data = JSON::Decode(JSON::Encode($xml), JSON::FORCE_ARRAY);
      } catch (JsonException $e) {
        throw new WrongCredentialsException("The ticket '$ticket' cannot be validated as the response from the server is corrupted or incomplete.");
      }
    } else {
        throw new WrongCredentialsException("The ticket '$ticket' cannot be validated as the CUNI CAS service is unavailable.");
    }

    return $data;
  }

  /**
   * Create correct URL for validation of the token.
   * @param $ticket
   * @param $clientUrl
   * @return string The URL for validation of the ticket.
   */
  private function getValidationUrl($ticket, $clientUrl) {
    $service = urlencode($clientUrl);
    $ticket = urlencode($ticket);
    return "{$this->casHttpBaseUri}serviceValidate?service={$service}&ticket={$ticket}&format=xml";
  }

  /**
   * Convert the data from the JSON response to the UserData container.
   * @param $ticket
   * @param $data
   * @return UserData
   * @throws CASMissingInfoException
   * @throws WrongCredentialsException
   */
  private function getUserData($ticket, $data): UserData {
    try {
      $info = Arrays::get($data, [/*"serviceResponse",*/ "authenticationSuccess", "attributes"]);
    } catch (InvalidArgumentException $e) {
      throw new WrongCredentialsException("The ticket '$ticket' is not valid and does not belong to a CUNI student or staff or it was already used.");
    }

    try {
      $ukco = LDAPHelper::getScalar(Arrays::get($info, $this->ukcoField));
      $emails = LDAPHelper::getArray(Arrays::get($info, $this->emailField));
      $firstName = LDAPHelper::getScalar(Arrays::get($info, $this->firstNameField));
      $lastName = LDAPHelper::getScalar(Arrays::get($info, $this->lastNameField));
      $affiliation = LDAPHelper::getArray(Arrays::get($info, $this->affiliationField));
    } catch (InvalidArgumentException $e) {
      throw new CASMissingInfoException("The information of the user received from the CAS is incomplete.");
    }

    // we do not get information about the degrees of the user
    $role = $this->getUserRole($affiliation);
    return new UserData($ukco, $emails, $firstName, $lastName, "", "", $role);
  }

  /**
   * Get role for the given affiliation.
   * @param array $affiliation
   * @return null|string
   */
  private function getUserRole(array $affiliation): ?string {
    foreach ($this->supervisorAffiliations as $supervisorAffiliation) {
      if (array_search($supervisorAffiliation, $affiliation) !== false) {
        return User::SUPERVISOR_ROLE;
      }
    }

    return null;
  }

}
