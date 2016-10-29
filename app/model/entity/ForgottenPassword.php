<?php

namespace App\Model\Entity;

use \DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class ForgottenPassword
{
  use \Kdyby\Doctrine\Entities\MagicAccessors;

  public function __construct(
    User $user,
    string $sentTo,
    string $redirectUrl,
    string $IPaddress
  ) {
    $this->user = $user;
    $this->sentTo = $sentTo;
    $this->requestedAt = new DateTime;
    $this->redirectUrl = $redirectUrl;
    $this->IPaddress = $IPaddress;
  }

  /**
   * @ORM\Id
   * @ORM\Column(type="guid")
   * @ORM\GeneratedValue(strategy="UUID")
   */
  protected $id;

  /**
   * @ORM\ManyToOne(targetEntity="User")
   */
  protected $user;

  /**
   * @ORM\Column(type="datetime")
   */
  protected $requestedAt;

  /**
   * @ORM\Column(type="string")
   */
  protected $sentTo;

  /**
   * @ORM\Column(type="string")
   */
  protected $redirectUrl;

  /**
   * @ORM\Column(type="string")
   */
  protected $IPaddress;

}
