<?php

namespace App\Helpers\ExternalLogin;

use App\Model\Entity\Instance;
use App\Model\Entity\Role;
use App\Model\Entity\User;

final class UserData {

  /** @var string */
  private $id;

  /** @return string */
  public function getId() { return $this->id; }

  /** @var string */
  private $firstName;

  /** @var string */
  private $lastName;

  /** @var string */
  private $email;

  /** @return string */
  public function getEmail() { return $this->email; }

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

  public function createEntity(Instance $instance, Role $role) {
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
