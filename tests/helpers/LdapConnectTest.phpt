<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\LdapUserUtils;
use App\Exceptions\WrongCredentialsException;
use App\Exceptions\LdapConnectException;


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
    Assert::equal(LdapUserUtils::ERROR_NO_SUCH_OBJECT, LdapUserUtils::getErrorCode("Could not bind user: Ldap Error Code=32 - No such object"));
  }

  public function testPersonalIdExtraction() {
    Assert::equal("54726191", LdapUserUtils::getPersonalId("cuniPersonalId=54726191,ou=people,dc=cuni,dc=cz"));
    Assert::equal("123", LdapUserUtils::getPersonalId("differentPersonalId=123,ou=people,dc=sth,dc=com"));
    Assert::equal("547261911234567", LdapUserUtils::getPersonalId("id=547261911234567,ou=people,dc=cuni,dc=cz"));
    Assert::equal(null, LdapUserUtils::getPersonalId(""));
    Assert::equal(null, LdapUserUtils::getPersonalId("asldkjasdlkjasldkj"));
  }

  public function testInvalidArgumentConfig() {
    Assert::exception(function() {
        $utils = new LdapUserUtils(self::$invalidArgumentConfig);
        $utils->getUser("ABC", "XYZ");
    }, 'App\Exceptions\LdapConnectException');
  }

  public function testWrongConfig() {
    Assert::exception(function() {
        $utils = new LdapUserUtils(self::$wrongConfig);
        $utils->getUser("ABC", "XYZ");
    }, 'App\Exceptions\LdapConnectException');
  }

  public function testWrongCredentials() {
    $ldapManager = new LdapUserUtils(self::$config);
    Assert::exception(function() use ($ldapManager) {$ldapManager->getUser("12345678", "password");}, 'App\Exceptions\WrongCredentialsException');
  }

  // public function testCorrectCredentials() {
  //   $ldapManager = new LdapUserUtils(self::$config);
  //   Assert::equal("ptr.stef@gmail.com", $ldapManager->getUser("54726191", "password"));
  // }

  public function testFindValidUserByMail() {
    $ldapManager = new LdapUserUtils(self::$config);
    Assert::equal("54726191", $ldapManager->findUserByMail("ptr.stef@gmail.com"));
  }

  public function testFindInvalidUserByMail() {
    $ldapManager = new LdapUserUtils(self::$config);
    Assert::equal(null, $ldapManager->findUserByMail("ukco@example.com"));
  }

}

# Testing methods run
$testCase = new LdapConnectTest;
$testCase->run();
