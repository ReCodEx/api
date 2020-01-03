<?php

namespace App\Helpers\ExternalLogin;

/**
 * Interface to external login service. Each implementation must provide
 * following methods for working inside ReCodEx solution. Custom identity
 * provider wrappers can be implemented for example for GitHub, Google,
 * MojeID and others.
 */
interface IExternalLoginService
{
    /**
     * Gets identifier for this service
     * @return string Login service unique identifier
     */
    function getServiceId(): string;

    /**
     * Gets the identifier of the type of authentication of the service.
     * @return string Login service unique identifier
     */
    function getType(): string;

    /**
     * Read user's data from the identity provider, if the credentials provided by the user are correct.
     * @param array $credentials
     * @param bool $onlyAuthenticate If true, only identifiers are relevant (external ID, email). No need fetching real name, role, ...
     * @return UserData Information known about this user
     */
    function getUser($credentials, bool $onlyAuthenticate = false): ?UserData;
}
