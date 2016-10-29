<?php

namespace App\Helpers\ExternalLogin;

/**
 * Interface to external login service. Each implementation must provide
 * following methods for working inside ReCodEx solution. Custom identity
 * provider wrappers can be implemented for example for GitHub, Google,
 * MojeID and others.
 */
interface IExternalLoginService {
  /**
   * Gets identifier for this service
   * @return string Login service unique identifier
   */
  function getServiceId(): string;

  /**
   * Read user's data from the identity provider, if the credentials provided by the user are correct.
   * @param  string $username Email or identification number of the person
   * @param  string $password User's password
   * @return UserData Information known about this user
   */
  function getUser(string $username, string $password): UserData;

}
