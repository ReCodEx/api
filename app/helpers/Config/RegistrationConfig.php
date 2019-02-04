<?php

namespace App\Helpers;
use Nette;
use Nette\Utils\Arrays;

class RegistrationConfig
{
  use Nette\SmartObject;

  protected $enabled;

  protected $implicitGroupsIds;

  public function __construct($config)
  {
    $this->enabled = Arrays::get($config, "enabled", false);
    $this->implicitGroupsIds = Arrays::get($config, "implicitGroupsIds", []);
  }

  public function isEnabled(): bool
  {
    return $this->enabled;
  }

  public function getImplicitGroupsIds(): array
  {
    return $this->implicitGroupsIds;
  }
}
