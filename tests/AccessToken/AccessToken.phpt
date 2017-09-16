<?php
include "../bootstrap.php";

use App\Exceptions\InvalidArgumentException;
use App\Security\AccessToken;
use App\Security\AccessManager;
use Tester\Assert;
use App\Exceptions\InvalidAccessTokenException;

class TestAccessToken extends Tester\TestCase
{

  public function testNoUserId() {
    $payload = new \stdClass;
    $token = new AccessToken($payload);
    Assert::exception(function () use ($token) {
      $token->getUserId();
    }, InvalidAccessTokenException::CLASS);
  }

  public function testGetUserId() {
    $payload = new \stdClass;
    $payload->sub = 123;
    $token = new AccessToken($payload);
    Assert::same("123", $token->getUserId());
  }

  public function testEmptyScope() {
    $payload = new \stdClass;
    $token = new AccessToken($payload);
    Assert::false($token->isInScope("bla bla"));
  }

  public function testWrongScope() {
    $payload = new \stdClass;
    $payload->scopes = [ "alb alb" ];
    $token = new AccessToken($payload);
    Assert::false($token->isInScope("bla bla"));
  }

  public function testCorrectScope() {
    $payload = new \stdClass;
    $payload->scopes = [ "bla bla" ];
    $token = new AccessToken($payload);
    Assert::true($token->isInScope("bla bla"));
  }

  public function testPredefinedScopes() {
    Assert::same("refresh", AccessToken::SCOPE_REFRESH);
    Assert::same("change-password", AccessToken::SCOPE_CHANGE_PASSWORD);
  }

  public function testCustomClaim() {
    $payload = new \stdClass;
    $payload->xyz = 123;
    $token = new AccessToken($payload);
    Assert::same(123, $token->getPayload("xyz"));
  }

  public function testMissingCustomClaim() {
    $payload = new \stdClass;
    $payload->abc = 123;
    $token = new AccessToken($payload);
    Assert::throws(function () use ($token) { $token->getPayload("xyz"); }, InvalidArgumentException::class);
  }


}

$testCase = new TestAccessToken();
$testCase->run();
