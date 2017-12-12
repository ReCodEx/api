<?php
namespace App\Security;
use App\Model\Entity\User;
use Nette;

class Identity implements Nette\Security\IIdentity
{
  use Nette\SmartObject;

  /** @var User */
  private $user;

  /** @var AccessToken */
  private $token;

  public const UNAUTHENTICATED_ROLE = "unauthenticated";

  public function __construct(?User $user, ?AccessToken $token)
  {
    $this->user = $user;
    $this->token = $token;
  }

  /**
   * Returns the ID of user.
   * @return mixed
   */
  function getId()
  {
    return $this->user ? $this->user->getId() : null;
  }

  /**
   * Returns a list of roles that the user is a member of.
   * @return array
   */
  function getRoles()
  {
    return $this->user ? [$this->user->getRole()] : [self::UNAUTHENTICATED_ROLE];
  }

  function getUserData()
  {
    return $this->user;
  }

  public function getToken()
  {
    return $this->token;
  }

  function isInScope($scope)
  {
    return $this->token ? $this->token->isInScope($scope) : FALSE;
  }
}