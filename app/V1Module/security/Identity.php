<?php
namespace App\Security;
use App\Model\Entity\User;
use Nette;

class Identity extends Nette\Object implements Nette\Security\IIdentity
{
  /** @var User */
  private $user;

  /** @var AccessToken */
  private $token;

  public function __construct(User $user, AccessToken $token)
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
    return $this->user->id;
  }

  /**
   * Returns a list of roles that the user is a member of.
   * @return array
   */
  function getRoles()
  {
    return [$this->user->role->id];
  }

  function getUserData()
  {
    return $this->user;
  }

  function isInScope($scope)
  {
    return $this->token->isInScope($scope);
  }
}