<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Exceptions\InvalidArgumentException;
use Nette\Security\Passwords;
use Nette\Utils\Validators;

/**
 * @ORM\Entity
 *
 * @method string getId()
 * @method string getUsername()
 * @method setUsername(string $username)
 * @method setPasswordHash(string $hash)
 * @method User getUser()
 */
class Login
{
    use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;

    /**
     * @ORM\Id
     * @ORM\Column(type="guid")
     * @ORM\GeneratedValue(strategy="UUID")
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

    const HASHING_OPTIONS = [
        "cost" => 11
    ];

    /**
     * TODO: This has to be done better! Move it somewhere else!
     */
    private static function createPasswordUtils(): Passwords {
        return new Passwords(PASSWORD_DEFAULT, self::HASHING_OPTIONS);
    }

    /**
     * TODO: This has to be done better! Move it somewhere else!
     * Hash the password accordingly.
     * @param string $password Plaintext password
     * @return string Password hash
     */
    public static function hashPassword($password)
    {
        return self::createPasswordUtils()->hash($password);
    }

    /**
     * Change the password to the given one (the password will be hashed).
     * @param string $password New password
     */
    public function changePassword($password)
    {
        $this->setPasswordHash(self::hashPassword($password));
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
     * @return bool
     */
    public function passwordsMatchOrEmpty($password)
    {
        if (empty($this->passwordHash) && empty($password)) {
            // quite special situation, but can happen if user registered using CAS
            // and already have existing local account
            return true;
        }

        return $this->passwordsMatch($password);
    }

    /**
     * Verify that the given password matches the stored password.
     * @param string $password The password given by the user
     * @return bool
     */
    public function passwordsMatch($password)
    {
        if (empty($this->passwordHash) || empty($password)) {
            return false;
        }

        // TODO: This has to be done better! Move it somewhere else!
        $passwordUtils = self::createPasswordUtils();
        if ($passwordUtils->verify($password, $this->passwordHash)) {
            if ($passwordUtils->needsRehash($this->passwordHash)) {
                $this->passwordHash = self::hashPassword($password);
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
     * @throws InvalidArgumentException
     */
    public static function createLogin(User $user, string $email, string $password)
    {
        if (Validators::isEmail($email) === false) {
            throw new InvalidArgumentException("email", "Username must be a valid email address.");
        }

        $login = new Login();
        $login->username = $email;
        $login->passwordHash = "";
        if (!empty($password)) {
            $login->passwordHash = self::hashPassword($password);
        }
        $login->user = $user;

        $user->setLogin($login);
        return $login;
    }
}
