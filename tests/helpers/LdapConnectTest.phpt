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
    'bindName' => "cunipersonalid"
  ];

  static $invalidArgumentConfig = [
    'hostname' => "padl.cuni.cz",
    'base_dn' => "ou=people,dc=cuni,dc=cz",
    'security' => "my_unknown_security",
    'bindName' => "cunipersonalid"
  ];

  static $wrongConfig = [
    'hostname' => "padl.cuni.cz",
    'base_dn' => "ou=people,dc=cuni,dc=cz",
    'port' => 388,
    'bindName' => "cunipersonalid"
  ];

  public function testBindString() {
    $ldapManager = new LdapUserUtils(self::$config);
    Assert::equal("cunipersonalid=123456789,ou=people,dc=cuni,dc=cz", $ldapManager->getBindString(123456789));
  }

  public function testErrorCodeExtraction() {
    Assert::equal(-1, LdapUserUtils::getErrorCode("Could not bind privileged user: Ldap Error Code=-1 - Invalid credentials"));
    Assert::equal(LdapUserUtils::ERROR_WRONG_CREDENTIALS, LdapUserUtils::getErrorCode("Could not bind privileged user: Ldap Error Code=49 - Invalid credentials"));
    Assert::equal(LdapUserUtils::ERROR_TOO_MANY_UNSUCCESSFUL_TRIES, LdapUserUtils::getErrorCode("Could not bind privileged user: Ldap Error Code=19 - Constraint violation"));
    Assert::equal(LdapUserUtils::ERROR_INAPPROPRIATE_AUTHENTICATION, LdapUserUtils::getErrorCode("Could not bind privileged user: Ldap Error Code=48 - Inappropriate authentication"));
  }

  public function testInvalidArgumentConfig() {
    $ldapManager = new LdapUserUtils(self::$invalidArgumentConfig);
    Assert::exception(function() use ($ldapManager) {$ldapManager->getUser("12345678", "password");}, 'App\Exception\LdapConnectException');
  }

  public function testWrongConfig() {
    $ldapManager = new LdapUserUtils(self::$wrongConfig);
    Assert::exception(function() use ($ldapManager) {$ldapManager->getUser("12345678", "password");}, 'App\Exception\LdapConnectException');
  }

  public function testWrongCredentials() {
    $ldapManager = new LdapUserUtils(self::$config);
    Assert::exception(function() use ($ldapManager) {$ldapManager->getUser("12345678", "password");}, 'App\Exception\WrongCredentialsException');
  }

//   public function testCorrectCredentials() {
//     $ldapManager = new LdapUserUtils(self::$config);
//     Assert::equal("ptr.stef@gmail.com", $ldapManager->getUser("54726191", "password"));
//   }

}

# Testing methods run
$testCase = new LdapConnectTest;
$testCase->run();
