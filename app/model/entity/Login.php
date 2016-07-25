<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Nette\Security\Passwords;

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

  public function getPasswordHash() { return $this->passwordHash; }

  /**
   * @ORM\OneToOne(targetEntity="User")
   */
  protected $user;

  const HASHING_OPTIONS = [
    'cost' => 11
  ];

  public static function hashPassword($password) {
    return Passwords::hash($password, self::HASHING_OPTIONS);
  }

  public function passwordsMatch($password) {
    if (Passwords::verify($password, $this->passwordHash)) {
      if (Passwords::needsRehash($this->passwordHash, self::HASHING_OPTIONS)) {
        $this->password = self::hashPassword($password);
      }

      return TRUE;
    }

    return FALSE;
  }

}
