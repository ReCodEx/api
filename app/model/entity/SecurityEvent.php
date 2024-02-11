<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use DateTime;
use JsonSerializable;

/**
 * @ORM\Entity
 * @ORM\Table(indexes={@ORM\Index(name="event_created_at_idx", columns={"created_at"})})
 * A logged security event such as user loggin or token refresh.
 */
class SecurityEvent implements JsonSerializable
{
    use CreateableEntity;

    public const TYPE_LOGIN = 'login';
    public const TYPE_LOGIN_EXTERNAL = 'loginext';
    public const TYPE_REFRESH = 'refresh';
    public const TYPE_ISSUE_TOKEN = 'token';
    public const TYPE_INVALIDATE_TOKENS = 'invalid';
    public const TYPE_CHANGE_PASSWORD = 'password';

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(type="string")
     * Must match one of the TYPE constants
     */
    protected $type;

    /**
     * @ORM\Column(type="string")
     */
    protected $remoteAddr;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     */
    protected $user;

    /**
     * @ORM\Column(type="text", length=65535)
     * Additional JSON encoded data (type-specific)
     */
    protected $data;

    /**
     * Create a new event record
     * @param string $type of the event (one of the TYPE enum values)
     * @param string $remoteAddr from which the action was taken
     * @param User $user involved in the action
     * @param array $data additional information encoded in JSON
     */
    private function __construct(string $type, string $remoteAddr, User $user, array $data = [])
    {
        $this->type = $type;
        $this->remoteAddr = $remoteAddr;
        $this->user = $user;
        $this->data = $data ? json_encode($data) : '';
        $this->createdAt = new DateTime();
    }

    /**
     * Record the user log-in (using internal credentials) event.
     * @param string $remoteAddr from which the action was taken
     * @param User $user involved in the action
     * @return SecurityEvent
     */
    public static function createLoginEvent(string $remoteAddr, User $user): SecurityEvent
    {
        return new self(self::TYPE_LOGIN, $remoteAddr, $user);
    }

    /**
     * Record the user log-in (using external authenticator) event.
     * @param string $remoteAddr from which the action was taken
     * @param User $user involved in the action
     * @return SecurityEvent
     */
    public static function createExternalLoginEvent(string $remoteAddr, User $user): SecurityEvent
    {
        return new self(self::TYPE_LOGIN_EXTERNAL, $remoteAddr, $user);
    }

    /**
     * Record the security token refresh event.
     * @param string $remoteAddr from which the action was taken
     * @param User $user involved in the action
     * @return SecurityEvent
     */
    public static function createRefreshTokenEvent(string $remoteAddr, User $user): SecurityEvent
    {
        return new self(self::TYPE_REFRESH, $remoteAddr, $user);
    }

    /**
     * Record the event when the user explicitly requests a (restricted) security token.
     * @param string $remoteAddr from which the action was taken
     * @param User $user involved in the action
     * @return SecurityEvent
     */
    public static function createIssueTokenEvent(string $remoteAddr, User $user): SecurityEvent
    {
        return new self(self::TYPE_ISSUE_TOKEN, $remoteAddr, $user);
    }

    /**
     * Record the event when the user invalidated all past tokens.
     * @param string $remoteAddr from which the action was taken
     * @param User $user involved in the action
     * @return SecurityEvent
     */
    public static function createInvalidateTokensEvent(string $remoteAddr, User $user): SecurityEvent
    {
        return new self(self::TYPE_INVALIDATE_TOKENS, $remoteAddr, $user);
    }

    /**
     * Record the change password event.
     * @param string $remoteAddr from which the action was taken
     * @param User $user involved in the action
     * @return SecurityEvent
     */
    public static function createChangePasswoedEvent(string $remoteAddr, User $user): SecurityEvent
    {
        return new self(self::TYPE_CHANGE_PASSWORD, $remoteAddr, $user);
    }

    /**
     * Enable automatic serialization to JSON
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->getId(),
            'type' => $this->type,
            'remoteAddr' => $this->remoteAddr,
            'user' => $this->user->getId(),
            'data' => $this->getData(),
            'createdAt' => $this->getCreatedAt()->getTimestamp(),
        ];
    }

    /*
     * Accessors
     */

    public function getId(): int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getRemoteAddr(): string
    {
        return $this->remoteAddr;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getData(): ?array
    {
        return $this->data ? json_decode($this->data) : null;
    }
}
