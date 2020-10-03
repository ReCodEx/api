<?php

namespace App\Helpers;

use Nette;
use Nette\Utils\Arrays;

class WorkerFilesConfig
{
    use Nette\SmartObject;

    /**
     * @var bool Whether the worker files core API feature is enabled at all.
     */
    protected $enabled;

    /**
     * @var string|null Credentials the worker must pass via HTTP basic auth to verify its claim.
     */
    protected $authUsername;

    /**
     * @var string|null Credentials the worker must pass via HTTP basic auth to verify its claim.
     */
    protected $authPassword;

    /**
     * @var string The longest time a file can exist without being used
     */
    protected $removalThreshold;


    public function __construct($config)
    {
        $this->enabled = (bool)Arrays::get($config, "enabled", false);
        $this->authUsername = Arrays::get($config, ["auth", "username"]);
        $this->authPassword = Arrays::get($config, ["auth", "password"]);
        $this->removalThreshold = Arrays::get($config, "removalThreshold", "1 day");
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getAuthUsername(): ?string
    {
        return $this->authUsername;
    }

    public function getAuthPassword(): ?string
    {
        return $this->authPassword;
    }

    public function getRemovalThreshold(): string
    {
        return $this->removalThreshold;
    }
}
