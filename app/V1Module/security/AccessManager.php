<?php

namespace App\Security;

use App\Model\Entity\User;
use App\Model\Repository\Users;

use App\Exception\InvalidAccessTokenException;
use App\Exception\NoAccessTokenException;
use App\Exception\ForbiddenRequestException;

use Nette\Http\Request;
use Nette\Utils\Strings;

use Firebase\JWT\JWT;
use DomainException;
use UnexpectedValueException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;

class AccessManager {

  /** @var Users  Users repository */
  protected $users;

  public function __construct(Users $users) {
    $this->users = $users;
  }

  /**
   * @param   Request
   * @return  User
   */
  public function getUserFromRequestOrThrow(Request $req) {
    $token = $this->getGivenAccessToken($req);
    if ($token === NULL) {
      throw new NoAccessTokenException;
    }

    JWT::$leeway = 60; // @todo load this from config!!

    try {
      $decodedToken = JWT::decode($token, $this->getSecretVerificationKey(), $this->getAllowedAlgs());
    } catch (DomainException $e) {
      throw new InvalidAccessTokenException($token);
    } catch (UnexpectedValueException $e) {
      throw new InvalidAccessTokenException($token);
    } catch (DomainException $e) {
      throw new InvalidAccessTokenException($token);
    } catch (ExpiredException $e) {
      throw new InvalidAccessTokenException($token);
    } catch (SignatureInvalidException $e) {
      throw new ForbiddenRequestException();
    } catch (BeforeValidException $e) {
      throw new InvalidAccessTokenException($token);
    }

    if (!isset($decodedToken->sub) || !isset($decodedToken->sub->id)) {
      throw new InvalidAccessTokenException($token);
    }

    $user = $this->users->get($decodedToken->sub->id);
    if (!$user || $user->isAllowed() === FALSE) {
      throw new ForbiddenRequestException;
    }

    return $user;
  }

  /**
   * @param   User
   * @return  string
   */
  public function issueToken(User $user) {    
    $tokenPayload = [
      "iss" => "https://recodex.projekty.ms.mff.cuni.cz", // @todo load this from config!!
      "aud" => "https://recodex.projekty.ms.mff.cuni.cz", // @todo load this from config!!
      "iat" => time(),
      "nbf" => time(),
      "exp" => time() + 1*24*60*60, // @todo load this from config!!
      "sub" => $user
    ];

    return JWT::encode($tokenPayload, $this->getSecretVerificationKey(), $this->getAlg());
  }

  private function getSecretVerificationKey() {
    return "recodex-123"; // @todo make this secure using environment variables - it must not appear on GitHub...
  }

  private function getAllowedAlgs() {
    // allowed algs must be separated from the used algs - if the algorithm is changed in the future,
    // we must accept the older algorithm until all the old tokens expire
    return [ "HS256" ]; // @todo load this from config!!
  }

  private function getAlg() {
    return "HS256"; // @todo load this from config!!
  }

  /**
   * Extract the access token from the request.
   * @return string|null  The access token parsed from the HTTP request, or FALSE if there is no access token.
   */
  public function getGivenAccessToken(Request $request) {
    $accessToken = $request->getQuery("access_token");
    if($accessToken !== NULL) return $accessToken; // the token is specified in the URL

    // if the token is not in the URL, try to find the "Authorization" header with the bearer token
    $authorizationHeader = $request->getHeader("Authorization", NULL);
    $parts = Strings::split($authorizationHeader, "/ /");
    if(count($parts) === 2) {
      list($bearer, $accessToken) = $parts;
      if($bearer === "Bearer" && !Strings::contains($accessToken, " ")) {
        return $accessToken;
      }
    }

    return NULL; // there is no access token or it could not be parsed
  }

}
