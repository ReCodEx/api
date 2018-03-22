<?php

namespace App\Security;

use App\Model\Entity\User;
use App\Model\Repository\Users;

use App\Exceptions\InvalidAccessTokenException;
use App\Exceptions\ForbiddenRequestException;

use Nette\Http\IRequest;
use Nette\Utils\Strings;
use Nette\Utils\Arrays;

use Firebase\JWT\JWT;
use DomainException;
use UnexpectedValueException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;

class AccessManager {

  /** @var Users  Users repository */
  protected $users;

  /** @var string Identification of the issuer of the token */
  private $issuer;

  /** @var string Identification of the audience of the token */
  private $audience;

  /** @var string[] Allowed algorithms for the encoding of the signature */
  private $allowedAlgorithms;

  /** @var string Name of the algorithm currently used for encrypting the signature of the token. */
  private $usedAlgorithm;

  /** @var string Verification key */
  private $verificationKey;

  /** @var int Expiration time of newly issued tokens (in seconds) */
  private $expiration;

  public function __construct(array $parameters, Users $users) {
    $this->users = $users;
    $this->verificationKey = Arrays::get($parameters, "verificationKey");
    $this->expiration = Arrays::get($parameters, "expiration", 24 * 60 * 60); // one day in seconds
    $this->issuer = Arrays::get($parameters, "issuer", "https://recodex.mff.cuni.cz");
    $this->audience = Arrays::get($parameters, "audience", "https://recodex.mff.cuni.cz");
    $this->allowedAlgorithms = Arrays::get($parameters, "allowedAlgorithms", [ "HS256" ]);
    $this->usedAlgorithm = Arrays::get($parameters, "usedAlgorithm", "HS256");
    JWT::$leeway = Arrays::get($parameters, "leeway", 10); // 10 seconds
  }

  /**
   * Parse and validate a JWT token and extract the payload.
   * @param string $token The potential JWT token
   * @return AccessToken|object The decoded payload
   * @throws ForbiddenRequestException
   * @throws InvalidAccessTokenException
   */
  public function decodeToken($token): AccessToken {
    try {
      $decodedToken = JWT::decode($token, $this->verificationKey, $this->allowedAlgorithms);
    } catch (DomainException $e) {
      throw new InvalidAccessTokenException($token);
    } catch (UnexpectedValueException $e) {
      throw new InvalidAccessTokenException($token);
    } catch (ExpiredException $e) {
      throw new InvalidAccessTokenException($token);
    } catch (SignatureInvalidException $e) {
      throw new ForbiddenRequestException();
    } catch (BeforeValidException $e) {
      throw new InvalidAccessTokenException($token);
    }

    if (!isset($decodedToken->sub)) {
      throw new InvalidAccessTokenException($token);
    }

    return new AccessToken($decodedToken);
  }

  /**
   * @param AccessToken $token Valid JWT payload
   * @return User
   * @throws ForbiddenRequestException
   */
  public function getUser(AccessToken $token): User {
    /** @var User $user */
    $user = $this->users->get($token->getUserId());
    if (!$user || $user->isAllowed() === false) {
      throw new ForbiddenRequestException;
    }

    return $user;
  }

  /**
   * Issue a new JWT for the user with optional scopes and optional explicit expiration time.
   * @param   User $user
   * @param   string[] $scopes Array of scopes
   * @param   int $exp Expiration of the token in seconds
   * @param   array $payload
   * @return string
   */
  public function issueToken(User $user, array $scopes = [], int $exp = null, array $payload = []) {
    if ($exp === null) {
      $exp = $this->expiration;
    }

    $token = new AccessToken((object) array_merge(
      $payload,
      [
        "iss" => $this->issuer,
        "aud" => $this->audience,
        "iat" => time(),
        "nbf" => time(),
        "exp" => time() + $exp,
        "sub" => $user->getId(),
        "scopes" => $scopes
      ]
    ));

    return $token->encode($this->verificationKey, $this->usedAlgorithm);
  }

  /**
   * Extract the access token from the request.
   * @return string|null  The access token parsed from the HTTP request, or null if there is no access token.
   */
  public static function getGivenAccessToken(IRequest $request) {
    $accessToken = $request->getQuery("access_token");
    if($accessToken !== null && Strings::length($accessToken) > 0) {
      return $accessToken; // the token specified in the URL is prefered
    }

    // if the token is not in the URL, try to find the "Authorization" header with the bearer token
    $authorizationHeader = $request->getHeader("Authorization", null);

    if ($authorizationHeader === null) {
      return null;
    }

    $parts = Strings::split($authorizationHeader, "/ /");
    if(count($parts) === 2) {
      list($bearer, $accessToken) = $parts;
      if($bearer === "Bearer" && !Strings::contains($accessToken, " ") && Strings::length($accessToken) > 0) {
        return $accessToken;
      }
    }

    return null; // there is no access token or it could not be parsed
  }

}
