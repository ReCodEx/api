<?php

namespace App\Helpers;

use Toyota\Component\Ldap\Core\Manager;
use Toyota\Component\Ldap\Platform\Native\Driver;
use Toyota\Component\Ldap\Exception\BindException;
use Toyota\Component\Ldap\Exception\ConnectionException;
use Toyota\Component\Ldap\Exception\OptionException;

use App\Exception\WrongCredentialsException;
use App\Exception\LdapConnectException;


class LdapUserUtils {

  /**
   * Configuration for initial connection to LDAP server. Requires
   * 'hostname' and 'base_dn', optionally 'port' and 'security'. For
   * more info see https://github.com/mrdm-nl/ldap.
   */
  private $ldapConfig;

  /** Name of userId element (such as cn or cunipersonalid) */
  private $bindName;

  /** Name of mail element (such as mail) */
  private $mailName;

  public function __construct(array $config) {
    $this->ldapConfig = [
      'hostname' => $config['hostname'],
      'base_dn' => $config['base_dn']
    ];
    if (array_key_exists('port', $config)) {
      $this->ldapConfig['port'] = $config['port'];
    }
    if (array_key_exists('security', $config)) {
      $this->ldapConfig['security'] = $config['security'];
    }

    $this->bindName = $config['bindName'];
    $this->mailName = $config['mailName'];
  }

  /**
   * Validates user against LDAP database.
   * @param string $userId user identifier, for example UKCO
   * @param string $password user's password
   * @throws WrongCredentialsException when supplied username or password is incorrect 
   * @return user's mail address
   */
  public function validateUser(string $userId, string $password) {
    try {
      $manager = new Manager($this->ldapConfig, new Driver());
    } catch (\InvalidArgumentException $e) {
      throw new LdapConnectException();
    }

    try {
      $manager->connect();
    } catch (ConnectionException $e) {
      throw new LdapConnectException();
    }

    $bindString = sprintf("%s=%s,%s", $this->bindName, $userId, $this->ldapConfig['base_dn']);
    try {
      $manager->bind($bindString, $password);
    } catch (BindException $e) {
      if (strpos($e->getMessage(), "-1") === FALSE) {
        throw new WrongCredentialsException();
      } else {
        throw new LdapConnectException();
      }
    }

    $node = $manager->getNode($bindString);
    return $node->get($this->mailName)->getValues()[0];
  }
}