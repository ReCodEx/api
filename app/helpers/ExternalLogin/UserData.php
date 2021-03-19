<?php

namespace App\Helpers\ExternalLogin;

use App\Exceptions\InvalidArgumentException;
use App\Model\Entity\Instance;
use App\Model\Entity\User;

/**
 * Common data about user every identity provider should know.
 */
final class UserData
{
    /** @var string Unique user identifier inside identity provider's system */
    private $id;

    /** @var string First name of user */
    private $firstName;

    /** @var string Last name of user */
    private $lastName;

    /** @var string Degrees before user's name' */
    private $degreesBeforeName = '';

    /** @var string Degrees after user's name */
    private $degreesAfterName = '';

    /** @var string Email address of user */
    private $mail;

    /** @var string|null Role which created user should have */
    private $role = null;

    // Read-only accessors
    public function __isset($name)
    {
        return isset($this->$name);
    }

    public function __get($name)
    {
        return $this->$name;
    }

    /**
     * Initialize the structure from raw decoded data.
     * @param array|object $data from decoded token
     */
    public function __construct($data)
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
            $this->degreesBeforeName,
            $this->degreesAfterName,
            $this->role,
            $instance
        );
    }
}
