<?php
namespace App\Security;

use Nette\StaticClass;

class Roles {
  use StaticClass;

  const UNAUTHENTICATED = "unauthenticated";
  const STUDENT = "student";
  const SUPERVISOR = "supervisor";
  const SUPERADMIN = "superadmin";
}