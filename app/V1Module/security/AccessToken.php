<?php

namespace App\Security;

use App\Exceptions\InvalidAccessTokenException;
use App\Exceptions\InvalidArgumentException;
use stdClass;

class AccessToken {

  // predefined scopes
  const SCOPE_REFRESH = "refresh";
  const SCOPE_CHANGE_PASSWORD = "change-password";
  const SCOPE_EMAIL_VERIFICATION = "email-verification";

  /** @var string|NULL The subject */
  private $sub = NULL;

  /** @var string[] Array of scopes this access can access */
  private $scopes = [];

  /** @var  stdClass Payload of the token */
  private $payload;

  /**
   * Create a wrapper for a given JWT payload.
   * @param object $payload The decoded payload of the token
   */
  public function __construct($payload) {
    if (isset($payload->sub)) {
      $this->sub = (string) $payload->sub;
    }

    if (isset($payload->scopes)) {
      $this->scopes = $payload->scopes;
    }

    $this->payload = $payload;
  }

  /**
   * Extract user's id from the token payload
   * @return string
   * @throws InvalidAccessTokenException
   */
  public function getUserId(): string {
    if ($this->sub === NULL) {
      throw new InvalidAccessTokenException("Missing the required 'sub' parameter of the token payload.");
    }

    return $this->sub;
  }

  /**
   * Verify that this token is allowed to access given scope.
   * @param string $scope The examined scope
   * @return bool
   */
  public function isInScope(string $scope): bool {
    return in_array($scope, $this->scopes);
  }

  /**
   * Access any claim of the payload.
   * @param $key
   * @return mixed
   * @throws InvalidArgumentException
   */
  public function getPayload($key) {
    if (!isset($this->payload->$key)) {
      throw new InvalidArgumentException("The payload of the access token does not contain claim '$key'");
    }

    return $this->payload->$key;
  }
}
