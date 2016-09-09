<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\LdapUserUtils;
use App\Exception\WrongCredentialsException;
use App\Exception\LdapConnectException;


class LdapConnectTest extends Tester\TestCase
{
  
  static $config = [
    'hostname' => "ldap.cuni.cz",
    'base_dn' => "ou=people,dc=cuni,dc=cz",
    'bindName' => "cunipersonalid",
    'mailName' => "mail"
  ];

  static $invalidArgumentConfig = [
    'hostname' => "padl.cuni.cz",
    'base_dn' => "ou=people,dc=cuni,dc=cz",
    'security' => "my_unknown_security",
    'bindName' => "cunipersonalid",
    'mailName' => "mail"
  ];

  static $wrongConfig = [
    'hostname' => "padl.cuni.cz",
    'base_dn' => "ou=people,dc=cuni,dc=cz",
    'port' => 388,
    'bindName' => "cunipersonalid",
    'mailName' => "mail"
  ];

  public function testInvalidArgumentConfig() {
    $ldapManager = new LdapUserUtils(self::$invalidArgumentConfig);
    Assert::exception(function() use ($ldapManager) {$ldapManager->validateUser("12345678", "password");}, 'App\Exception\LdapConnectException');
  }

  public function testWrongConfig() {
    $ldapManager = new LdapUserUtils(self::$wrongConfig);
    Assert::exception(function() use ($ldapManager) {$ldapManager->validateUser("12345678", "password");}, 'App\Exception\LdapConnectException');
  }

  public function testWrongCredentials() {
    $ldapManager = new LdapUserUtils(self::$config);
    Assert::exception(function() use ($ldapManager) {$ldapManager->validateUser("12345678", "password");}, 'App\Exception\WrongCredentialsException');
  }

  /*public function testCorrectCredentials() {
    $ldapManager = new LdapUserUtils(self::$config);
    Assert::equal("ptr.stef@gmail.com", $ldapManager->validateUser("54726191", "password"));
  }*/

}

# Testing methods run
$testCase = new LdapConnectTest;
$testCase->run();
