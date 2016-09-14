<?php

namespace App\Helpers\ExternalLogin;

interface IExternalLoginService {

  function getServiceId(): string; 
  function getUser(string $username, string $password): UserData;

}
