<?php

namespace App\Security;

use Nette\StaticClass;

class TokenScope
{
    use StaticClass;

    public const CHANGE_PASSWORD = "change-password";
    public const MASTER = "master";
    public const EMAIL_VERIFICATION = "email-verification";
    public const REFRESH = "refresh";
    public const READ_ALL = "read-all";
    public const PLAGIARISM = "plagiarism";
}
