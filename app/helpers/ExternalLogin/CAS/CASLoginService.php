<?php

namespace App\Helpers\ExternalLogin\CAS;

use App\Exceptions\InvalidArgumentException as AppInvalidArgumentException;
use App\Helpers\ExternalLogin\IExternalLoginService;
use App\Helpers\ExternalLogin\UserData;
use App\Exceptions\WrongCredentialsException;
use App\Exceptions\CASMissingInfoException;

use App\Model\Entity\User;
use App\Security\Roles;
use Nette\InvalidArgumentException;
use Nette\Utils\Arrays;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Tracy\ILogger;

use GuzzleHttp\Psr7\Request;
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
class CASLoginService implements IExternalLoginService {

  /** @var string Unique identifier of this login service, for example "cas-uk" */
  private $serviceId;

  /**
   * Gets identifier for this service
   * @return string Login service unique identifier
   */
  public function getServiceId(): string { return $this->serviceId; }

  /**
   * @return string The CAS authentication
   */
  public function getType(): string { return "cas"; }

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

  /** @var array Array containing identifiers which registers person as a submit in retrieved affiliation */
  private $studentAffiliations;

  /** @var array Array containing identifiers which registers person as a supervisor in retrieved affiliation */
  private $supervisorAffiliations;

  /** @var string The base URI for the validation of login tickets */
  private $casHttpBaseUri;

  /**
   * @var ILogger
   */
  private $logger;

  /**
   * Constructor
   * @param string $serviceId Identifier of this login service, must be unique
   * @param array $options
   * @param array $fields
   */
  public function __construct(string $serviceId, array $options, array $fields, ILogger $logger) {
    $this->serviceId = $serviceId;

    // The field names of user's information stored in the CAS LDAP
    $this->ukcoField = Arrays::get($fields, "ukco", "cunipersonalid");
    $this->affiliationField = Arrays::get($fields, "affiliation", "edupersonscopedaffiliation");
    $this->studentAffiliations = Arrays::get($fields, "studentAffiliations", []);
    $this->supervisorAffiliations = Arrays::get($fields, "supervisorAffiliations", []);
    $this->emailField = Arrays::get($fields, "email", "mail");
    $this->firstNameField = Arrays::get($fields, "firstName", "givenname");
    $this->lastNameField = Arrays::get($fields, "lastName", "sn");

    // The CAS HTTP validation endpoint
    $this->casHttpBaseUri = Arrays::get($options, "baseUri", "https://idp.cuni.cz/cas/");
    $this->logger = $logger;
  }

  /**
   * Read user's data from the identity provider, if the ticket provided by the user is valid
   * @param array $credentials
   * @param bool $onlyAuthenticate If true, only ID is required to be valid in returned user data.
   * @return UserData Information known about this user
   * @throws AppInvalidArgumentException
   * @throws CASMissingInfoException
   */
  public function getUser($credentials, bool $onlyAuthenticate = false): UserData {
    $ticket = Arrays::get($credentials, "ticket", null);
    $clientUrl = Arrays::get($credentials, "clientUrl", null);

    if ($ticket === null || $clientUrl === null) {
        throw new AppInvalidArgumentException("The ticket or the client URL is missing for validation of the request.");
    }

    $info = $this->validateTicket($ticket, $clientUrl);
    return $this->getUserData($ticket, $info, $onlyAuthenticate);
  }

  /**
   * Internal XML parsing routine for ticket response.
   * @param string $ticket
   * @param string $body String representation of the response body.
   * @param string $namespace XML namespace URI, if detected.
   * @return \SimpleXMLElement representing the response body.
   * @throws WrongCredentialsException If the XML could not have been parsed.
   */
  private function parseXMLBody(string $ticket, string $body, string $namespace = '')
  {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($body, 'SimpleXMLElement', 0, $namespace);
    $err = libxml_get_errors();
    if ($err) {
      $this->logger->log("CAS Ticket validation returned following response:\n$body", ILogger::DEBUG);
      foreach ($err as $e) {
        // Internal XML errors are logges as warnings
        $this->logger->log($e, ILogger::WARNING);
      }
      throw new WrongCredentialsException("The ticket '$ticket' cannot be validated as the response from the server is corrupted or incomplete.");
    }
    return $xml;
  }

  /**
   * @param string $ticket
   * @param string $clientUrl
   * @return array
   * @throws WrongCredentialsException
   */
  private function validateTicket(string $ticket, string $clientUrl) {
    $client = new Client();
    $url = $this->getValidationUrl($ticket, $clientUrl);
    $req = new Request('GET', $url);
    $res = $client->send($req);
    $data = null;

    if ($res->getStatusCode() === 200) { // the response should be 200 even if the ticket is invalid
      try {
        $body = (string)$res->getBody();

        // Parse XML (twice, if necessary, to get right namespace) ...
        $xml = $this->parseXMLBody($ticket, $body);
        $namespaces = $xml->getDocNamespaces();
        if ($namespaces) {
          $namespace = empty($namespaces['cas']) ? reset($namespaces) : $namespaces['cas'];
          $xml = $this->parseXMLBody($ticket, $body, $namespace);
        }

        // A trick that utilizes JSON serialization of SimpleXML objects to convert the XML into an array.
        $data = JSON::decode(JSON::encode((array)$xml), JSON::FORCE_ARRAY);
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
    return "{$this->casHttpBaseUri}p3/serviceValidate?service={$service}&ticket={$ticket}&format=xml";
  }

  /**
   * Convert the data from the JSON response to the UserData container.
   * @param $ticket
   * @param $data
   * @param bool $onlyAuthenticate
   * @return UserData
   * @throws CASMissingInfoException
   * @throws WrongCredentialsException
   */
  private function getUserData($ticket, $data, bool $onlyAuthenticate = false): UserData {
    try {
      $info = Arrays::get($data, ["authenticationSuccess", "attributes"]);
    } catch (InvalidArgumentException $e) {
      $this->logger->log("Ticket validation did not return successful response with attributes:\n" . var_export($data, true), ILogger::ERROR);
      throw new WrongCredentialsException("The ticket '$ticket' is not valid and does not belong to a CUNI student or staff or it was already used.");
    }

    try {
      $ukco = LDAPHelper::getScalar(Arrays::get($info, $this->ukcoField));
      $firstName = LDAPHelper::getScalar(Arrays::get($info, $this->firstNameField));
      $lastName = LDAPHelper::getScalar(Arrays::get($info, $this->lastNameField));
    } catch (InvalidArgumentException $e) {
      $this->logger->log("The user attributes received from the CAS are incomplete:\n" . var_export($data, true), ILogger::ERROR);
      throw new CASMissingInfoException("The user attributes received from the CAS are incomplete.");
    }

    try {
      // Email is separated, because it is more common error (so the user gets more accurate exception message).
      $emails = LDAPHelper::getArray(Arrays::get($info, $this->emailField));
    } catch (InvalidArgumentException $e) {
      $this->logger->log("The user attributes received from the CAS are incomplete (email missing):\n" . var_export($data, true), ILogger::ERROR);
      throw new CASMissingInfoException("The user attributes received from the CAS do not contain an email address, which is required.");
    }

    if ($onlyAuthenticate) {
      return new UserData($ukco, $emails, "", "", "", "");
    }

    try {
      $affiliation = LDAPHelper::getArray(Arrays::get($info, $this->affiliationField));
    } catch (InvalidArgumentException $e) {
      // affiliation is not mandatory and can be omitted, just log it, as it is not standard behaviour
      $this->logger->log("The user attributes received from the CAS are missing 'affiliation' for user identification '$ukco'", ILogger::WARNING);
      $affiliation = [];
    }

    // we do not get information about the degrees of the user
    $role = $this->getUserRole($affiliation);
    if (!$role) {
      $aff = join(', ', $affiliation);
      $this->logger->log("Given 'affiliation' attributes ($aff) for user '$ukco' does not correspond to any role.", ILogger::ERROR);
      throw new CASMissingInfoException("The user attributes received from the CAS has no affiliation attributes that would allow registration in ReCodEx. Authenticated account does not belong to a student nor to an employee of MFF.");
    }

    return new UserData($ukco, $emails, $firstName, $lastName, "", "", $role);
  }

  /**
   * Get role for the given affiliation.
   * @param array $affiliation
   * @return null|string
   */
  private function getUserRole(array $affiliation): ?string {
    foreach ($this->studentAffiliations as $studentAffiliation) {
      if (array_search($studentAffiliation, $affiliation) !== false) {
        return Roles::STUDENT_ROLE;
      }
    }

    foreach ($this->supervisorAffiliations as $supervisorAffiliation) {
      if (array_search($supervisorAffiliation, $affiliation) !== false) {
        return Roles::SUPERVISOR_ROLE;
      }
    }

    return null;
  }

}
