<?php

namespace App\Helpers\ExternalLogin;

use App\Model\Entity\Instance;
use App\Model\Entity\Role;
use App\Model\Entity\User;

/**
 * Common data about user every identity provider should know.
 */
final class UserData {

  /** @var string Unique user identifier inside identity provider's system */
  private $id;

  /**
   * Get user identifier
   * @return string Unique user ID
   */
  public function getId() { return $this->id; }

  /** @var string First name of user */
  private $firstName;

  /** @var string Last name of user */
  private $lastName;

  /** @var string Email address of user */
  private $email;

  /**
   * get user's email address
   * @return string
   */
  public function getEmail() { return $this->email; }

  /**
   * Constructor
   * @param string                $id          Identifier of user (inside identity provider)
   * @param string                $firstName   First name of user
   * @param string                $lastName    Last name of user
   * @param string                $email       Email address of user
   * @param IExternalLoginService $authService Used authentification service provider class
   */
  public function __construct(
    string $id,
    string $firstName,
    string $lastName,
    string $email,
    IExternalLoginService $authService
  ) {
    $this->id = $id;
    $this->firstName = $firstName;
    $this->lastName = $lastName;
    $this->email = $email;
  }

  /**
   * Create database entity for current user
   * @param Instance $instance Used instance of ReCodEx
   * @param Role     $role     Base permission role for current user
   * @return User Database entity for the user
   */
  public function createEntity(Instance $instance, Role $role): User {
    return new User(
      $this->email,
      $this->firstName,
      $this->lastName,
      "", // @todo
      "", // @todo
      $role,
      $instance
    );
  }

}
