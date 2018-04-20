<?php
namespace App\Security;

use Nette\StaticClass;

class TokenScope {
  const CHANGE_PASSWORD = "change-password";
  const MASTER = "master";
  const EMAIL_VERIFICATION = "email-verification";
  const REFRESH = "refresh";

  use StaticClass;
}