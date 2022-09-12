<?php

namespace App\Security;

use App\Exceptions\InvalidAccessTokenException;
use App\Exceptions\InvalidArgumentException;
use Firebase\JWT\JWT;
use DateTime;

class InvitationToken
{
    /** @var array Payload of the token */
    private $payload;

    /**
     * Construct a new token from individual parameters.
     * @param int $expirationTime in seconds
     * @param string $instanceId
     * @param string $email
     * @param string $firstName
     * @param string $lastName
     * @param string $titlesBefore
     * @param string $titlesAfter
     * @param string[] $groupsIds list of IDs where the user is added after registration
     * @throws InvalidAccessTokenException if the data are not correct
     */
    public static function create(
        int $expirationTime,
        string $instanceId,
        string $email,
        string $firstName,
        string $lastName,
        string $titlesBefore = "",
        string $titlesAfter = "",
        array $groupsIds = []
    ) {
        return new self([
            "iid" => $instanceId,
            "eml" => $email,
            "iat" => time(),
            "exp" => time() + $expirationTime,
            "usr" => [ $titlesBefore, $firstName, $lastName, $titlesAfter ],
            "grp" => $groupsIds,
        ]);
    }

    /**
     * Create token from JWT payload.
     * @param array $payload The decoded/constructed payload of the token
     * @throws InvalidAccessTokenException if validation fails
     */
    public function __construct(array $payload)
    {
        $props = [ "iid" => "string", "eml" => "string", "iat" => "integer", "exp" => "integer", "usr" => "array"];
        foreach ($props as $name => $type) {
            if (!array_key_exists($name, $payload) || gettype($payload[$name]) !== $type) {
                throw new InvalidAccessTokenException(
                    "Invitation token payload property '$name' is missing or of a wrong type."
                );
            }
        }

        foreach ($payload["usr"] as $value) {
            if (!is_string($value)) {
                throw new InvalidAccessTokenException(
                    "Invitation token payload property 'usr' must be an array of strings."
                );
            }
        }

        if (count($payload["usr"]) !== 4) {
            throw new InvalidAccessTokenException(
                "Invitation token payload property 'usr' must have exactly four parts."
            );
        }

        if (array_key_exists("grp", $payload)) {
            if (!is_array($payload["grp"])) {
                throw new InvalidAccessTokenException("Invitation token payload property 'grp' is not an array.");
            }

            foreach ($payload["grp"] as $id) {
                if (!is_string($id)) {
                    throw new InvalidAccessTokenException(
                        "Invitation token payload property 'grp' must be an array of group IDs."
                    );
                }
            }
        }

        $this->payload = $payload;
    }

    /**
     * Return ID of the instance where the user is being invited.
     * @return string
     */
    public function getInstanceId(): string
    {
        return $this->payload["iid"];
    }

    public function getUserName(): string
    {
        list($_, $firstName, $lastName) = $this->payload["usr"];
        return "$firstName $lastName";
    }

    public function getEmail(): string
    {
        return $this->payload["eml"];
    }

    /**
     * Get data needed for constructing the user entity.
     * @return array [ email, first name, last name, titles before, titles after ]
     */
    public function getUserData(): array
    {
        list($titlesBefore, $firstName, $lastName, $titlesAfter) = $this->payload["usr"];
        return [ $this->payload["eml"], $firstName, $lastName, $titlesBefore, $titlesAfter ];
    }

    /**
     * Return a list of groups to which the user is being invited.
     * @return string[]
     */
    public function getGroupsIds(): array
    {
        return $this->payload["grp"] ?? [];
    }

    public function getIssuedAt(): int
    {
        return $this->payload["iat"];
    }

    public function getExpireAt(): DateTime
    {
        return new DateTime('@' . $this->payload["exp"]);
    }

    public function hasExpired(): bool
    {
        return $this->payload["exp"] < time();
    }

    public function encode(string $verificationKey, string $usedAlgorithm): string
    {
        return JWT::encode($this->payload, $verificationKey, $usedAlgorithm);
    }
}
