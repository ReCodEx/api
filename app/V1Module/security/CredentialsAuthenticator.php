<?php
namespace App\Security;
use App\Exceptions\WrongCredentialsException;
use App\Model\Repository\Logins;
use Nette;

class CredentialsAuthenticator
{
  use Nette\SmartObject;

  /** @var Logins */
  private $logins;

  public function __construct(Logins $logins)
  {
    $this->logins = $logins;
  }

  /**
   * @param string $username
   * @param string $password
   * @return \App\Model\Entity\User
   * @throws WrongCredentialsException
   */
  function authenticate(string $username, string $password)
  {
    $user = $this->logins->getUser($username, $password);

    if ($user === null) {
      throw new WrongCredentialsException("Invalid credentials");
    }

    return $user;
  }
}