<?php

namespace App\Helpers;

use Toyota\Component\Ldap\Core\Manager;
use Toyota\Component\Ldap\Core\Node;
use Toyota\Component\Ldap\Platform\Native\Driver;
use Toyota\Component\Ldap\Exception\BindException;
use Toyota\Component\Ldap\Exception\ConnectionException;

use App\Exceptions\WrongCredentialsException;
use App\Exceptions\LdapConnectException;

use Nette\Utils\Arrays;
use Nette\Utils\Strings;

/**
 * Utilities for LDAP communication. This tries to be more general, but some methods may
 * be a little prepared for usage in cooperation with CAS authentication system of Charles
 * University.
 */
class LdapUserUtils {

  const ERROR_TOO_MANY_UNSUCCESSFUL_TRIES = 19;
  const ERROR_INAPPROPRIATE_AUTHENTICATION = 48;
  const ERROR_WRONG_CREDENTIALS = 49;
  const ERROR_NO_SUCH_OBJECT = 32;

  /**
   * @var array
   * Configuration for initial connection to LDAP server. Requires
   * 'hostname' and 'base_dn', optionally 'port' and 'security'. For
   * more info see https://github.com/mrdm-nl/ldap.
   */
  private $ldapConfig;

  /** @var string LDAP's base_dn field */
  private $baseDn;

  /** @var string Name of userId element (such as cn or cunipersonalid) */
  private $bindName;

  /** @var Manager|NULL Anonymous LDAP connection for searching */
  private $anonymousManager = NULL;

  /**
   * Access anonymous connection manager.
   * @return Manager
   * @throws LdapConnectException
   */
  protected function getAnonymousManager() {
    if ($this->anonymousManager === NULL) {
      try {
        $this->anonymousManager = $this->connect();
        $this->anonymousManager->bind();
      } catch (\Exception $e) {
        throw new LdapConnectException;
      }
    }

    return $this->anonymousManager;
  }

  /**
   * Constructor with initialization of config and anonymous connection to LDAP
   * @param array $config LDAP configuration
   * @throws LdapConnectException if anonymous connection fails
   */
  public function __construct(array $config) {
    $this->ldapConfig = [
      'hostname' => Arrays::get($config, 'hostname', NULL),
      'base_dn' => Arrays::get($config, 'base_dn', NULL)
    ];

    if (array_key_exists('port', $config)) {
      $this->ldapConfig['port'] = $config['port'];
    }

    if (array_key_exists('security', $config)) {
      $this->ldapConfig['security'] = $config['security'];
    }

    $this->baseDn = Arrays::get($config, 'base_dn');
    $this->bindName = Arrays::get($config, 'bindName');
  }

  /**
   * Validates user against LDAP database and returns the information from the service.
   * @param string $userId user identifier, for example UKCO
   * @param string $password user's password
   * @throws WrongCredentialsException when supplied username or password is incorrect
   * @throws LdapConnectException when ldap server cannot be reached
   * @return Node User's data
   */
  public function getUser(string $userId, string $password): Node {
    $bindString = $this->getBindString($userId);
    $manager = $this->connect();
    try {
      $manager->bind($bindString, $password);
    } catch (BindException $e) {
      $code = self::getErrorCode($e->getMessage());
      switch ($code) {
        case self::ERROR_INAPPROPRIATE_AUTHENTICATION:
          throw new WrongCredentialsException("This account cannot be used for authentication to ReCodEx. The password is probably not verified.");

        case self::ERROR_WRONG_CREDENTIALS:  // wrong password
          throw new WrongCredentialsException;

        case self::ERROR_NO_SUCH_OBJECT:  // wrong username (ukco)
          throw new WrongCredentialsException;

        case self::ERROR_TOO_MANY_UNSUCCESSFUL_TRIES:
          throw new WrongCredentialsException("Too many unsuccessful tries. You won't be able to log in for a short amount of time.");

        default:
          throw new LdapConnectException;
      }
    }

    return $manager->getNode($bindString);
  }

  /**
   * Find unique user identifier for email supplied (anonymous finding).
   * @param string $mail      Email address to be searched
   * @param string $mailField LDAP email field, defaults to 'mail'
   * @return string|NULL Unique user ID or NULL
   */
  public function findUserByMail(string $mail, string $mailField = 'mail') {
    $results = $this->getAnonymousManager()->search(
      $this->baseDn,
      "(&(objectClass=person)({$mailField}={$mail}))"
    );

    $dn = $results->key();

    // the ID can be extracted only if there is exactely one result
    if ($dn === NULL || $results->next() !== NULL) {
      return NULL;
    }

    return self::getPersonalId($dn);
  }

  /**
   * Return binding string to LDAP for given user identifier
   * @param string $userId User identifier
   * @return string LDAP binding string
   */
  public function getBindString(string $userId): string {
    return "{$this->bindName}={$userId},{$this->baseDn}";
  }

  /**
   * Connecting to LDAP
   * @return Manager Connected LDAP manager instance
   * @throws LdapConnectException on error
   */
  private function connect() {
    try {
      $manager = new Manager($this->ldapConfig, new Driver());
    } catch (\InvalidArgumentException $e) {
      throw new LdapConnectException;
    }

    try {
      $manager->connect();
    } catch (ConnectionException $e) {
      throw new LdapConnectException;
    }

    return $manager;
  }

  /**
   * Extract the code of the error from the message.
   * @param  string $msg The error messate
   * @return int The code
   */
  public static function getErrorCode(string $msg): int {
    list($code) = Strings::match($msg, "/-?\d+/");
    if ($code === NULL) {
      throw new LdapConnectException; // The bind exception didn't yield correct error message
    }

    return intval($code);
  }

  /**
   * Extracts the personal ID from distinguished name of the node. It is assumed, that the ID is the value of the left most component of the DN.
   * @param  string $dn   Distinguished name of the node (i.e.: cuniPersonalId=54726191,ou=people,dc=cuni,dc=cz)
   * @return string|NULL  The personal ID
   */
  public static function getPersonalId(string $dn) {
    $parts = ldap_explode_dn($dn, 1); // 1 ==> only values of RDN, see http://php.net/manual/en/function.ldap-explode-dn.php
    if ($parts === FALSE || $parts["count"] === 0) {
      return NULL;
    }

    unset($parts["count"]);
    return reset($parts); // the first value is the left most component
  }

}
