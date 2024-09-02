<?php

namespace App\Security;

use Nette\StaticClass;

/**
 * Namespace for scope constants.
 */
class TokenScope
{
    use StaticClass;

    /**
     * The default scope with no additional restrictions.
     */
    public const MASTER = "master";

    /**
     * Read-only scope restricts operations to data retrieval only.
     */
    public const READ_ALL = "read-all";

    /**
     * Used by 3rd party plagiarism detection tools to fetch solutions and feed similarities back.
     */
    public const PLAGIARISM = "plagiarism";

    /**
     * Operations with reference solutions only. Can be used to insert additional solutions (e.g., created by GPT),
     * as reference solutions to exercises.
     */
    public const REF_SOLUTIONS = "ref-solutions";

    /**
     * Special scope used in password-retrieval links. The user can only change the local password.
     */
    public const CHANGE_PASSWORD = "change-password";

    /**
     * Special scope used in password verification links. The user can only mark email address verified.
     */
    public const EMAIL_VERIFICATION = "email-verification";

    /**
     * Scope used for 3rd party tools designed to externally manage groups and student memeberships.
     */
    public const GROUP_EXTERNAL_ATTRIBUTES = "group-external-attributes";

    /**
     * Scope for managing the users. Used in case the user data needs to be updated from an external database.
     */
    public const USERS = "users";

    /**
     * Usually used in combination with other scopes. Allows refreshing the token.
     */
    public const REFRESH = "refresh";
}
