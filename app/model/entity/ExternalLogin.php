<?php

namespace App\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Exceptions\InvalidArgumentException;

use Nette\Security\Passwords;
use Nette\Utils\Validators;

/**
 * @ORM\Entity
 */
class ExternalLogin
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
  protected $authService;

  /**
   * @ORM\Column(type="string")
   */
  protected $externalId;

  /**
   * @ORM\ManyToOne(targetEntity="User")
   */
  protected $user;

  public function __construct(User $user, string $authService, string $externalId) {
    $this->user = $user;
    $this->authService = $authService;
    $this->externalId = $externalId;
  }

}
