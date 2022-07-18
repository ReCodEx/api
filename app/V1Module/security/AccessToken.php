<?php

namespace App\Security;

use App\Exceptions\InvalidAccessTokenException;
use App\Exceptions\InvalidArgumentException;
use Firebase\JWT\JWT;
use stdClass;

class AccessToken
{
    /** @var string|null The subject */
    private $sub = null;

    /** @var string|null Effective user role of this token */
    private $effrole = null;

    /** @var string[] Array of scopes this access can access */
    private $scopes = [];

    /** @var stdClass Payload of the token */
    private $payload;

    /**
     * Create a wrapper for a given JWT payload.
     * @param object $payload The decoded payload of the token
     */
    public function __construct($payload)
    {
        if (isset($payload->sub)) {
            $this->sub = (string)$payload->sub;
        }

        if (isset($payload->scopes)) {
            $this->scopes = $payload->scopes;
        }

        if (isset($payload->effrole)) {
            $this->effrole = $payload->effrole;
        }

        $this->payload = $payload;
    }

    /**
     * Extract user's id from the token payload
     * @return string
     * @throws InvalidAccessTokenException
     */
    public function getUserId(): string
    {
        if ($this->sub === null) {
            throw new InvalidAccessTokenException("Missing the required 'sub' parameter of the token payload.");
        }

        return $this->sub;
    }

    /**
     * Verify that this token is allowed to access given scope.
     * @param string $scope The examined scope
     * @return bool
     */
    public function isInScope(string $scope): bool
    {
        return in_array($scope, $this->scopes);
    }

    public function getPayloadData(): array
    {
        return (array)$this->payload;
    }

    /**
     * Access any claim of the payload.
     * @param string $key
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function getPayload($key)
    {
        if (!isset($this->payload->$key)) {
            throw new InvalidArgumentException("The payload of the access token does not contain claim '$key'");
        }

        return $this->payload->$key;
    }

    /**
     * Access any claim of the payload. If the claim is not present, return a default value.
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getPayloadOrDefault($key, $default)
    {
        if (!isset($this->payload->$key)) {
            return $default;
        }

        return $this->payload->$key;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function getEffectiveRole(): ?string
    {
        return $this->effrole;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getIssuedAt(): int
    {
        return $this->getPayload("iat");
    }

    public function getExpirationTime(): int
    {
        return $this->getPayload("exp") - $this->getPayload("iat");
    }

    public function encode(string $verificationKey, string $usedAlgorithm): string
    {
        return JWT::encode((array)$this->payload, $verificationKey, $usedAlgorithm);
    }
}
