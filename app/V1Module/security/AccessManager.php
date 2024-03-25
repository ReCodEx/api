<?php

namespace App\Security;

use App\Model\Entity\User;
use App\Model\Repository\Users;
use App\Exceptions\InvalidAccessTokenException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\FrontendErrorMappings;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\Utils\Strings;
use Nette\Utils\Arrays;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use DomainException;
use UnexpectedValueException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;

class AccessManager
{
    /** @var Users  Users repository */
    protected $users;

    /** @var string Identification of the issuer of the token */
    private $issuer;

    /** @var string Identification of the audience of the token */
    private $audience;

    /** @var string Name of the algorithm currently used for encrypting the signature of the token. */
    private $usedAlgorithm;

    /** @var string Verification key */
    private $verificationKey;

    /** @var int Expiration time of newly issued tokens (in seconds) */
    private $expiration;

    /** @var int Expiration time of newly issued invitation tokens (in seconds) */
    private $invitationExpiration;

    public function __construct(array $parameters, Users $users)
    {
        $this->users = $users;
        $this->verificationKey = Arrays::get($parameters, "verificationKey");
        $this->expiration = Arrays::get($parameters, "expiration", 24 * 60 * 60); // one day in seconds
        $this->invitationExpiration = Arrays::get($parameters, "invitationExpiration", 24 * 60 * 60); // one day in sec
        $this->issuer = Arrays::get($parameters, "issuer", "https://recodex.mff.cuni.cz");
        $this->audience = Arrays::get($parameters, "audience", "https://recodex.mff.cuni.cz");
        $this->usedAlgorithm = Arrays::get($parameters, "usedAlgorithm", "HS256");
        JWT::$leeway = Arrays::get($parameters, "leeway", 10); // 10 seconds
    }

    public function getExpiration(): int
    {
        return $this->expiration;
    }

    /**
     * Parse and validate a JWT token and extract the payload.
     * @param string $token The potential JWT token
     * @return AccessToken The decoded payload
     * @throws ForbiddenRequestException
     * @throws InvalidAccessTokenException
     */
    public function decodeToken(string $token): AccessToken
    {
        try {
            $decodedToken = JWT::decode($token, new Key($this->verificationKey, $this->usedAlgorithm));
        } catch (DomainException $e) {
            throw new InvalidAccessTokenException($token, $e);
        } catch (UnexpectedValueException $e) {
            throw new InvalidAccessTokenException($token, $e);
        }

        if (!isset($decodedToken->sub)) {
            throw new InvalidAccessTokenException($token);
        }

        return new AccessToken($decodedToken);
    }

    /**
     * Parse and validate a JWT invitation token and extract the payload.
     * @param string $token The potential JWT token
     * @return InvitationToken The decoded payload wrapped in token class
     * @throws ForbiddenRequestException
     * @throws InvalidAccessTokenException
     */
    public function decodeInvitationToken(string $token): InvitationToken
    {
        try {
            $decodedToken = JWT::decode($token, new Key($this->verificationKey, $this->usedAlgorithm));
        } catch (DomainException $e) {
            throw new InvalidAccessTokenException($token, $e);
        } catch (UnexpectedValueException $e) {
            throw new InvalidAccessTokenException($token, $e);
        }

        return new InvitationToken((array)$decodedToken);
    }

    /**
     * @param AccessToken $token Valid JWT payload
     * @return User
     * @throws ForbiddenRequestException
     */
    public function getUser(AccessToken $token): User
    {
        /** @var ?User $user */
        $user = $this->users->get($token->getUserId());
        if (!$user) {
            throw new ForbiddenRequestException(
                "Forbidden Request - User does not exist",
                IResponse::S403_FORBIDDEN,
                FrontendErrorMappings::E403_001__USER_NOT_EXIST
            );
        }

        if (!$user->isAllowed()) {
            throw new ForbiddenRequestException(
                "Forbidden Request - User account was disabled",
                IResponse::S403_FORBIDDEN,
                FrontendErrorMappings::E403_002__USER_NOT_ALLOWED
            );
        }

        return $user;
    }

    /**
     * Issue a new JWT for the user with optional scopes and optional explicit expiration time.
     * @param User $user
     * @param string|null $effectiveRole Effective user role for issued token
     * @param string[] $scopes Array of scopes
     * @param int $exp Expiration of the token in seconds
     * @param array $payload
     * @return string
     * @throws ForbiddenRequestException
     */
    public function issueToken(
        User $user,
        string $effectiveRole = null,
        array $scopes = [],
        int $exp = null,
        array $payload = []
    ) {
        if (!$user->isAllowed()) {
            throw new ForbiddenRequestException(
                "Forbidden Request - User account was disabled",
                IResponse::S403_FORBIDDEN,
                FrontendErrorMappings::E403_002__USER_NOT_ALLOWED
            );
        }

        if ($exp === null) {
            $exp = $this->expiration;
        }

        $token = new AccessToken(
            (object)array_merge(
                $payload,
                [
                    "iss" => $this->issuer,
                    "aud" => $this->audience,
                    "iat" => time(),
                    "nbf" => time(),
                    "exp" => time() + $exp,
                    "sub" => $user->getId(),
                    "effrole" => $effectiveRole,
                    "scopes" => $scopes
                ]
            )
        );

        return $token->encode($this->verificationKey, $this->usedAlgorithm);
    }

    public function issueRefreshedToken(AccessToken $token): string
    {
        return $this->issueToken(
            $this->getUser($token),
            null,
            $token->getScopes(),
            $token->getExpirationTime(),
            $token->getPayloadData()
        );
    }

    /**
     * Create an invitation for a specific user pre-filling the basic user data and optionally
     * allowing the user to join selected groups.
     * @param string $instanceId
     * @param string $email
     * @param string $firstName
     * @param string $lastName
     * @param string $titlesBefore
     * @param string $titlesAfter
     * @param string[] $groupsIds list of IDs where the user is added after registration
     * @param int|null $invitationExpiration token expiration duration override (for testing purposes only)
     * @throws InvalidAccessTokenException if the data are not correct
     */
    public function issueInvitationToken(
        string $instanceId,
        string $email,
        string $firstName,
        string $lastName,
        string $titlesBefore = "",
        string $titlesAfter = "",
        array $groupsIds = [],
        int $invitationExpiration = null,
    ): string {
        $token = InvitationToken::create(
            $invitationExpiration ?? $this->invitationExpiration,
            $instanceId,
            $email,
            $firstName,
            $lastName,
            $titlesBefore,
            $titlesAfter,
            $groupsIds,
        );
        return $token->encode($this->verificationKey, $this->usedAlgorithm);
    }

    /**
     * Extract the access token from the request.
     * @return string|null  The access token parsed from the HTTP request, or null if there is no access token.
     */
    public static function getGivenAccessToken(IRequest $request)
    {
        $accessToken = $request->getQuery("access_token");
        if ($accessToken !== null && Strings::length($accessToken) > 0) {
            return $accessToken; // the token specified in the URL is prefered
        }

        // if the token is not in the URL, try to find the "Authorization" header with the bearer token
        $authorizationHeader = $request->getHeader("Authorization");

        if ($authorizationHeader === null) {
            return null;
        }

        $parts = Strings::split($authorizationHeader, "/ /");
        if (count($parts) === 2) {
            list($bearer, $accessToken) = $parts;
            if ($bearer === "Bearer" && !Strings::contains($accessToken, " ") && Strings::length($accessToken) > 0) {
                return $accessToken;
            }
        }

        return null; // there is no access token or it could not be parsed
    }
}
