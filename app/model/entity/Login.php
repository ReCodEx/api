<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Exceptions\InvalidArgumentException;
use Nette\Security\Passwords;
use Nette\Utils\Validators;

/**
 * @ORM\Entity
 */
class Login
{
    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class=\Ramsey\Uuid\Doctrine\UuidGenerator::class)
     * @var \Ramsey\Uuid\UuidInterface
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=128, unique=true)
     */
    protected $username;

    /**
     * @ORM\Column(type="string")
     */
    protected $passwordHash;

    /**
     * @ORM\OneToOne(targetEntity="User", inversedBy="login")
     */
    protected $user;

    /**
     * Hash the password accordingly.
     * @param string|null $password Plaintext password
     * @param Passwords $passwordsService injection of a service (we do not want to inject directly into entities)
     * @return string|null Password hash
     */
    public static function hashPassword($password, Passwords $passwordsService)
    {
        if ($password === null) {
            return "";
        }

        return $passwordsService->hash($password);
    }

    /**
     * Change the password to the given one (the password will be hashed).
     * @param string $password New password
     * @param Passwords $passwordsService injection of a service (we do not want to inject directly into entities)
     */
    public function changePassword($password, Passwords $passwordsService)
    {
        $this->setPasswordHash(self::hashPassword($password, $passwordsService));
    }

    /**
     * Clear user password.
     */
    public function clearPassword()
    {
        $this->setPasswordHash("");
    }

    /**
     * Determine if password hash is empty string.
     * @return bool
     */
    public function isPasswordEmpty(): bool
    {
        return empty($this->passwordHash);
    }

    /**
     * Verify that the given password matches the stored password. If the current
     * password is empty and given one too, the passwords are considered to match.
     * @param string $password The password given by the user
     * @param Passwords $passwordsService injection of a service (we do not want to inject directly into entities)
     * @return bool
     */
    public function passwordsMatchOrEmpty($password, Passwords $passwordsService)
    {
        if (empty($this->passwordHash) && empty($password)) {
            // quite special situation, but can happen if user registered using CAS
            // and already have existing local account
            return true;
        }

        return $this->passwordsMatch($password, $passwordsService);
    }

    /**
     * Verify that the given password matches the stored password.
     * @param string $password The password given by the user
     * @param Passwords $passwordsService injection of a service (we do not want to inject directly into entities)
     * @return bool
     */
    public function passwordsMatch($password, Passwords $passwordsService)
    {
        if (empty($this->passwordHash) || empty($password)) {
            return false;
        }

        if ($passwordsService->verify($password, $this->passwordHash)) {
            if ($passwordsService->needsRehash($this->passwordHash)) {
                $this->passwordHash = self::hashPassword($password, $passwordsService);
            }

            return true;
        }

        return false;
    }

    /**
     * Factory method.
     * @param User $user
     * @param string $email
     * @param string $password
     * @return Login
     * @param Passwords|null $passwordsService injection of a service (we do not want to inject directly into entities)
     *                       if null, the service is constructed inplace (special case to make fixtures work)
     * @throws InvalidArgumentException
     */
    public static function createLogin(User $user, string $email, string $password, Passwords $passwordsService = null)
    {
        if (Validators::isEmail($email) === false) {
            throw new InvalidArgumentException("email", "Username must be a valid email address.");
        }

        if ($passwordsService === null) {
            // this should happen only in fixtures!
            $passwordsService = new Passwords(PASSWORD_BCRYPT, ['cost' => 11]);
        }

        $login = new Login();
        $login->username = $email;
        $login->passwordHash = "";
        if (!empty($password)) {
            $login->passwordHash = self::hashPassword($password, $passwordsService);
        }
        $login->user = $user;

        $user->setLogin($login);
        return $login;
    }

    /*
     * Accessors
     */

    public function getId(): ?string
    {
        return $this->id === null ? null : (string)$this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(?string $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
