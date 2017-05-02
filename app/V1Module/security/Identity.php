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
    return $this->user->getId();
  }

  /**
   * Returns a list of roles that the user is a member of.
   * @return array
   */
  function getRoles()
  {
    return [$this->user->getRole()->getId()];
  }

  function getUserData()
  {
    return $this->user;
  }

  public function getToken() {
    return $this->token;
  }

  function isInScope($scope)
  {
    return $this->token->isInScope($scope);
  }
}