<?php

namespace App\Security;

use App\Exceptions\InvalidAccessTokenException;

class AccessToken {

  // predefined scopes
  const SCOPE_REFRESH = "refresh";
  const SCOPE_CHANGE_PASSWORD = "change-password";

  /** @var string The subject */
  private $sub = NULL;

  /** @var string[] Array of scopes this access can access */
  private $scopes = [];

  /**
   * Create a wrapper for a given JWT payload.
   * @param object $payload The decoded payload of the token
   */
  public function __construct($payload) {
    if (isset($payload->sub)) {
      $this->sub = $payload->sub;
    }

    if (isset($payload->scopes)) {
      $this->scopes = $payload->scopes;
    }
  }

  /**
   * Extract user's id from the token payload
   * @return string
   */
  public function getUserId(): string {
    if ($this->sub === NULL) {
      throw new InvalidAccessTokenException("Missing the required 'sub' parameter of the token payload.");
    }

    return (string) $this->sub;
  }

  /**
   * Verify that this token is allowed to access given scope.
   * @param string $scope The examined scope
   * @return bool
   */
  public function isInScope(string $scope): bool {
    return in_array($scope, $this->scopes);
  }

}
