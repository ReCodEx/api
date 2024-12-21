<?php

namespace App\Helpers;

use App\Model\Entity\Instance;
use App\Model\Entity\User;
use App\Exceptions\ConfigException;
use App\Security\TokenScope;
use Nette;
use Nette\Utils\Arrays;

/**
 * This holds a configuration and help handle tokens for a single extension.
 */
class ExtensionConfig
{
    use Nette\SmartObject;

    /**
     * Internal identifier.
     */
    private string $id;

    /**
     * Caption as a string or localized strings (array locale => caption).
     * @var string|string[]
     */
    private string|array $caption;

    /**
     * URL template for the external service. The template may hold the following placeholders:
     * - {token} - will be replaced with URL-encoded temporary token
     * - {locale} - will be replaced with a language identifier ('en', 'cs', ...) based on currently selected language
     */
    private string $url;

    /**
     * List of scopes that will be set to (full) access tokens generated after tmp-token verification.
     * @var string[]
     */
    private array $tokenScopes;

    /**
     * User override for (full) access tokens. This user will be used instead of user ID passed in tmp token.
     * This is a way how to safely provide more powerful full tokens (without compromising tmp tokens).
     * If null, the (logged in) user from tmp token is passed to the full token.
     */
    private string|null $tokenUserId = null;

    /**
     * List of instances in which the extension should appear.
     * Empty list = all instances.
     * @var string[]
     */
    private array $instances = [];

    /**
     * List of user roles for which this extensions should appear.
     * Empty list = all roles.
     * @var string[]
     */
    private array $userRoles = [];

    /**
     * List of eligible user external login types. A user must hava at least one of these logins to see the extension.
     * Empty list = no external logins are required.
     */
    private array $userExternalLogins = [];

    public function __construct(array $config)
    {
        $this->id = (string)Arrays::get($config, "id");

        $this->caption = Arrays::get($config, "caption");
        if (is_array($this->caption)) {
            foreach ($this->caption as $locale => $caption) {
                if (!is_string($locale) || !is_string($caption)) {
                    throw new ConfigException("Invalid extension caption format.");
                }
            }
        }

        $this->url = Arrays::get($config, "url");
        $this->tokenScopes = Arrays::get(
            $config,
            ["token", "scopes"],
            [ TokenScope::MASTER, TokenScope::REFRESH ]
        ) ?? [];
        $this->tokenUserId = Arrays::get($config, ["token", "user"], null);
        $this->instances = Arrays::get($config, "instances", []) ?? [];
        $this->userRoles = Arrays::get($config, ["user", "roles"], []) ?? [];
        $this->userExternalLogins = Arrays::get($config, ["user", "externalLogins"], []) ?? [];
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCaption(): string|array
    {
        return $this->caption;
    }

    /**
     * Get formatted URL. A template is injected a token and current locale.
     * @param string $token already serialized JWT
     * @param string $locale language identification ('en', 'cs', ...)
     * @return string an instantiated URL template
     */
    public function getUrl(string $token, string $locale): string
    {
        $url = $this->url;
        $url = str_replace('{token}', urlencode($token), $url);
        $url = str_replace('{locale}', urlencode($locale), $url);
        return $url;
    }

    /**
     * Check whether this extension is accessible by given user in given instance.
     * @param Instance $instance
     * @param User|null $user (if null, the extension must be accessible by all users)
     * @return bool true if the extension is accessible
     */
    public function isAccessible(Instance $instance, ?User $user): bool
    {
        if ($this->instances && !in_array($instance->getId(), $this->instances)) {
            return false;
        }

        if (!$user) {
            // test accessibility for all users (no user filters must be present)
            return !$this->userRoles && !$this->userExternalLogins;
        }

        if ($this->userRoles && !in_array($user->getRole(), $this->userRoles)) {
            return false;
        }

        if ($this->userExternalLogins) {
            $logins = $user->getConsolidatedExternalLogins();
            foreach ($this->userExternalLogins as $service) {
                if (array_key_exists($service, $logins)) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }
}
