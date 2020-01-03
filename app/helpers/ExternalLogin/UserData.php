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

    /**
     * Get user identifier
     * @return string Unique user ID
     */
    public function getId()
    {
        return $this->id;
    }

    /** @var string First name of user */
    private $firstName;

    /** @var string Last name of user */
    private $lastName;

    /** @var string Degrees before user's name' */
    private $degreesBeforeName;

    /** @var string Degrees after user's name */
    private $degreesAfterName;

    /** @var string[] Email address of user */
    private $emails;

    /** @var string|null Role which created user should have */
    private $role;

    /**
     * get user's email address
     * @return string[]
     */
    public function getEmails()
    {
        return $this->emails;
    }

    /**
     * Constructor
     * @param string $id Identifier of user (inside identity provider)
     * @param array $emails Email address of user
     * @param string $firstName First name of user
     * @param string $lastName Last name of user
     * @param string $degreesBeforeName Degrees before user's name
     * @param string $degreesAfterName Degrees after user's name
     * @param string|null $role
     * @throws InvalidArgumentException
     */
    public function __construct(
        string $id,
        array $emails,
        string $firstName,
        string $lastName,
        string $degreesBeforeName,
        string $degreesAfterName,
        string $role = null
    ) {
        // check if at least one email was given
        if (count($emails) === 0) {
            throw new InvalidArgumentException("LDAP user '$id' does not have any email specified");
        }

        $this->id = $id;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->emails = $emails;
        $this->degreesBeforeName = $degreesBeforeName;
        $this->degreesAfterName = $degreesAfterName;
        $this->role = $role;
    }

    /**
     * Create database entity for current user
     * @param Instance $instance Used instance of ReCodEx
     * @return User Database entity for the user
     */
    public function createEntity(Instance $instance): User
    {
        return new User(
            current($this->emails), // first email is picked
            $this->firstName,
            $this->lastName,
            $this->degreesBeforeName,
            $this->degreesAfterName,
            $this->role,
            $instance
        );
    }
}
