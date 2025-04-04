<?php

namespace App\Helpers\ExternalLogin;

use App\Model\Entity\Instance;
use App\Model\Entity\User;
use InvalidArgumentException;

/**
 * Common data about user every identity provider should know.
 */
final class UserData
{
    /** @var string Unique user identifier inside identity provider's system */
    private $id;

    public function getId(): string
    {
        return $this->id;
    }

    /** @var string First name of user */
    private $firstName;

    /** @var string Last name of user */
    private $lastName;

    /** @var string Titles before user's name' */
    private $titlesBeforeName = '';

    /** @var string Titles after user's name */
    private $titlesAfterName = '';

    /** @var string Email address of user */
    private $mail;

    public function getMail(): string
    {
        return $this->mail;
    }

    /** @var string|null Role which created user should have */
    private $role = null;

    public function getRole(): ?string
    {
        return $this->role;
    }

    /**
     * Initialize the structure from raw decoded data.
     * @param array|object $data from decoded token
     * @param string|null $defaultRole role set if no role is available in the data
     */
    public function __construct($data, ?string $defaultRole = null)
    {
        $data = (array)$data;

        foreach ($data as $name => $value) {
            if (property_exists($this, $name)) {
                $this->$name = $value;
            }
        }

        if (empty($this->id)) {
            throw new InvalidArgumentException("User's external identifier must be specified.");
        }

        if (empty($this->firstName) || empty($this->lastName)) {
            throw new InvalidArgumentException("User's full name must be specified.");
        }

        if (empty($this->mail)) {
            throw new InvalidArgumentException("User's e-mail address must be specified.");
        }

        if (!filter_var($this->mail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("User's e-mail address is not valid.");
        }

        if (!$this->role) {
            $this->role = $defaultRole;
        }
    }

    /**
     * Create database entity for current user
     * @param Instance $instance Used instance of ReCodEx
     * @return User Database entity for the user
     */
    public function createEntity(Instance $instance): User
    {
        return new User(
            $this->mail,
            $this->firstName,
            $this->lastName,
            $this->titlesBeforeName,
            $this->titlesAfterName,
            $this->role,
            $instance
        );
    }
}
