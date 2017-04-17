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

  /** @var string Degrees before user's name' */
  private $degreesBeforeName;

  /** @var string Degrees after user's name */
  private $degreesAfterName;

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
   * @param string                $email       Email address of user
   * @param string                $firstName   First name of user
   * @param string                $lastName    Last name of user
   * @param string                $degreesBeforeName   Degrees before user's name
   * @param string                $degreesAfterName    Degrees after user's name
   */
  public function __construct(
    string $id,
    string $email,
    string $firstName,
    string $lastName,
    string $degreesBeforeName,
    string $degreesAfterName
  ) {
    $this->id = $id;
    $this->firstName = $firstName;
    $this->lastName = $lastName;
    $this->email = $email;
    $this->degreesBeforeName = $degreesBeforeName;
    $this->degreesAfterName = $degreesAfterName;
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
      $this->degreesBeforeName,
      $this->degreesAfterName,
      $role,
      $instance
    );
  }

}
