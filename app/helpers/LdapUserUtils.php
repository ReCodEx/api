<?php

namespace App\Helpers;

use Toyota\Component\Ldap\Core\Manager;
use Toyota\Component\Ldap\Core\Node;
use Toyota\Component\Ldap\Platform\Native\Driver;
use Toyota\Component\Ldap\Exception\BindException;
use Toyota\Component\Ldap\Exception\ConnectionException;
use Toyota\Component\Ldap\Exception\OptionException;

use App\Exceptions\WrongCredentialsException;
use App\Exceptions\LdapConnectException;

use Nette\Utils\Arrays;
use Nette\Utils\Strings;


class LdapUserUtils {

  const ERROR_TOO_MANY_UNSUCCESSFUL_TRIES = 19;
  const ERROR_INAPPROPRIATE_AUTHENTICATION = 48;
  const ERROR_WRONG_CREDENTIALS = 49;
  const ERROR_NO_SUCH_OBJECT = 32;

  /**
   * Configuration for initial connection to LDAP server. Requires
   * 'hostname' and 'base_dn', optionally 'port' and 'security'. For
   * more info see https://github.com/mrdm-nl/ldap.
   */
  private $ldapConfig;

  /** @var string */
  private $baseDn;

  /** @var string Name of userId element (such as cn or cunipersonalid) */
  private $bindName;

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
          throw new WrongCredentialsException("This CAS account cannot be used for authentication to ReCodEx. The password is probably not verified.");

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

  public function getBindString($userId) {
    return "{$this->bindName}={$userId},{$this->baseDn}";
  }

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

  public static function getErrorCode(string $msg): int {
    list($code) = Strings::match($msg, "/-?\d+/");
    return intval($code);
  }

}
