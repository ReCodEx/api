<?php

namespace App\Helpers;

use Nette;
use Nette\Utils\Arrays;

class WorkerFilesConfig
{
    use Nette\SmartObject;

    protected $enabled;
    protected $authUsername;
    protected $authPassword;

    public function __construct($config)
    {
        $this->enabled = (bool)Arrays::get($config, "enabled", false);
        $this->authUsername = Arrays::get($config, ["auth", "username"]);
        $this->authPassword = Arrays::get($config, ["auth", "password"]);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getAuthUsername()
    {
        return $this->authUsername;
    }

    public function getAuthPassword()
    {
        return $this->authPassword;
    }
}
