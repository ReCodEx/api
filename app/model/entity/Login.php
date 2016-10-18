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
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\Column(type="string")
   */
  protected $username;

  /**
   * @ORM\Column(type="string")
   */
  protected $passwordHash;

  /**
   * @ORM\ManyToOne(targetEntity="User")
   */
  protected $user;

  const HASHING_OPTIONS = [
    "cost" => 11
  ];

  public static function hashPassword($password) {
    return Passwords::hash($password, self::HASHING_OPTIONS);
  }

  public function passwordsMatch($password) {
    if (Passwords::verify($password, $this->passwordHash)) {
      if (Passwords::needsRehash($this->passwordHash, self::HASHING_OPTIONS)) {
        $this->passwordHash = self::hashPassword($password);
      }

      return TRUE;
    }

    return FALSE;
  }

  public static function createLogin(User $user, string $email, string $password) {
    if (Validators::isEmail($email) === FALSE) {
      throw new InvalidArgumentException("email", "Username must be a valid email address.");
    }

    $login = new Login;
    $login->username = $email;
    $login->passwordHash = self::hashPassword($password);
    $login->user = $user;
    return $login;
  }

}
