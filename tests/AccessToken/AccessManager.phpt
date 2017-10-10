<?php
include "../bootstrap.php";

use App\Security\AccessToken;
use App\Security\AccessManager;
use Tester\Assert;
use App\Exceptions\InvalidAccessTokenException;
use App\Model\Repository\Users;

use Firebase\JWT\JWT;
use Nette\Http\UrlScript;
use Nette\Http\Request;

class TestAccessManager extends Tester\TestCase
{
  use MockeryTrait;

  /*
   * Token decoding
   */

  public function testDecodeToken() {
    $users = Mockery::mock(App\Model\Repository\Users::class);
    $verificationKey = "abc";
    $manager = new AccessManager([ "verificationKey" => $verificationKey ], $users);
    $payload = [ "sub" => "123", "exp" => time() + 123 ];
    $token = JWT::encode($payload, $verificationKey, "HS256");
    $accessToken = $manager->decodeToken($token);
    Assert::type(AccessToken::CLASS, $accessToken);
    Assert::equal("123", $accessToken->getUserId());
  }

  public function testDecodeUnverifiedToken() {
    $users = Mockery::mock(App\Model\Repository\Users::class);
    $verificationKey = "abc";
    $manager = new AccessManager([ "verificationKey" => $verificationKey ], $users);
    $payload = [ "sub" => "123", "exp" => time() + 123 ];
    $token = JWT::encode($payload, $verificationKey . "!!!", "HS256");

    Assert::exception(function() use ($manager, $token) {
      $manager->decodeToken($token);
    }, InvalidAccessTokenException::CLASS, "Access token '$token' is not valid.");
  }

  public function testDecodeExpiredToken() {
    $users = Mockery::mock(App\Model\Repository\Users::class);
    $verificationKey = "abc";
    $manager = new AccessManager([ "verificationKey" => $verificationKey ], $users);
    $payload = [ "sub" => "123", "exp" => time() - 123, "leeway" => 0 ];
    $token = JWT::encode($payload, $verificationKey, "HS256");

    Assert::exception(function() use ($manager, $token) {
      $manager->decodeToken($token);
    }, InvalidAccessTokenException::CLASS, "Access token '$token' is not valid.");
  }

  public function testDecodeExpiredTokenWithEnoughLeeway() {
    $users = Mockery::mock(App\Model\Repository\Users::class);
    $verificationKey = "abc";
    $manager = new AccessManager([ "verificationKey" => $verificationKey ], $users);
    $payload = [ "sub" => "123", "exp" => time() - 5, "leeway" => 10 ];
    $token = JWT::encode($payload, $verificationKey, "HS256");
    Assert::type(AccessToken::CLASS, $manager->decodeToken($token));
  }

  public function testDecodeTokenBeforeNBF() {
    $users = Mockery::mock(App\Model\Repository\Users::class);
    $verificationKey = "abc";
    $manager = new AccessManager([ "verificationKey" => $verificationKey ], $users);
    $payload = [ "sub" => "123", "exp" => time() + 1000, "nbf" => time() + 100 ];
    $token = JWT::encode($payload, $verificationKey, "HS256");

    Assert::exception(function() use ($manager, $token) {
      $manager->decodeToken($token);
    }, InvalidAccessTokenException::CLASS, "Access token '$token' is not valid.");
  }

  /*
   * Token issuing
   */

  public function testIssueToken() {
    $users = Mockery::mock(App\Model\Repository\Users::class);
    $verificationKey = "abc";
    $manager = new AccessManager([
      "verificationKey" => $verificationKey,
      "issuer" => "X",
      "audience" => "Y",
      "expiration" => 123
    ], $users);

    $user = Mockery::mock(App\Model\Entity\User::CLASS);
    $user->shouldReceive("getId")->andReturn("123456");
    $token = $manager->issueToken($user);

    $payload = JWT::decode($token, $verificationKey, ["HS256"]);
    Assert::equal($user->getId(), $payload->sub);
    Assert::equal("X", $payload->iss);
    Assert::equal("Y", $payload->aud);
    Assert::true((time() + 123) >= $payload->exp);
    Assert::equal(time(), $payload->nbf);
    Assert::equal(time(), $payload->iat);
    Assert::equal([], $payload->scopes);
  }

  public function testIssueTokenWithScopes() {
    $users = Mockery::mock(App\Model\Repository\Users::class);
    $verificationKey = "abc";
    $manager = new AccessManager([ "verificationKey" => $verificationKey ], $users);

    $user = Mockery::mock(App\Model\Entity\User::CLASS);
    $user->shouldReceive("getId")->andReturn("123456");
    $token = $manager->issueToken($user, [ "x", "y" ]);

    $payload = JWT::decode($token, $verificationKey, ["HS256"]);
    Assert::equal(["x", "y"], $payload->scopes);
  }

  public function testIssueTokenWithExplicitExpiration() {
    $users = Mockery::mock(App\Model\Repository\Users::class);
    $verificationKey = "abc";
    $manager = new AccessManager([ "verificationKey" => $verificationKey ], $users);

    $user = Mockery::mock(App\Model\Entity\User::CLASS);
    $user->shouldReceive("getId")->andReturn("123456");
    $token = $manager->issueToken($user, NULL, 30);

    $payload = JWT::decode($token, $verificationKey, ["HS256"]);
    Assert::true((time() + 30) >= $payload->exp);
  }

  public function testCustomPayload() {
    $users = Mockery::mock(App\Model\Repository\Users::class);
    $verificationKey = "abc";
    $manager = new AccessManager([ "verificationKey" => $verificationKey ], $users);

    $user = Mockery::mock(App\Model\Entity\User::CLASS);
    $user->shouldReceive("getId")->andReturn("123456");
    $token = $manager->issueToken($user, NULL, 30, [ "sub" => "abcde", "xyz" => "uvw" ]);

    $payload = JWT::decode($token, $verificationKey, ["HS256"]);
    Assert::true((time() + 30) >= $payload->exp);
    Assert::equal("123456", $payload->sub);
    Assert::equal("uvw", $payload->xyz);
  }

  /*
   * Access token extraction
   */

  public function testExtractFromQuery() {
    $token = "abcdefg";
    $url = new UrlScript("https://www.whatever.com/bla/bla/bla?x=y&access_token=$token");
    $request = new Request($url);
    Assert::equal($token, AccessManager::getGivenAccessToken($request));
  }

  public function testExtractFromEmptyQuery() {
    $token = "";
    $url = new UrlScript("https://www.whatever.com/bla/bla/bla?x=y&access_token=$token");
    $request = new Request($url);
    Assert::null(AccessManager::getGivenAccessToken($request));
  }

  public function testExtractFromHeader() {
    $token = "abcdefg";
    $url = new UrlScript("https://www.whatever.com/bla/bla/bla?x=y");
    $request = new Request($url, NULL, NULL, NULL, NULL, [ "Authorization" => "Bearer $token" ]);
    Assert::equal($token, AccessManager::getGivenAccessToken($request));
  }

  public function testExtractFromHeaderWrongType() {
    $token = "abcdefg";
    $url = new UrlScript("https://www.whatever.com/bla/bla/bla?x=y");
    $request = new Request($url, NULL, NULL, NULL, NULL, [ "Authorization" => "Basic $token" ]);
    Assert::null(AccessManager::getGivenAccessToken($request));
  }

  public function testExtractFromHeaderEmpty() {
    $token = "";
    $url = new UrlScript("https://www.whatever.com/bla/bla/bla?x=y");
    $request = new Request($url, NULL, NULL, NULL, NULL, [ "Authorization" => "Basic $token" ]);
    Assert::null(AccessManager::getGivenAccessToken($request));
  }

  public function testExtractFromHeaderWithSpace() {
    $token = "";
    $url = new UrlScript("https://www.whatever.com/bla/bla/bla?x=y");
    $request = new Request($url, NULL, NULL, NULL, NULL, [ "Authorization" => "Bearer $token and more!" ]);
    Assert::null(AccessManager::getGivenAccessToken($request));
  }
}

$testCase = new TestAccessManager();
$testCase->run();
