<?php
namespace App\Helpers;
use Nette;
use Nette\Utils\Arrays;

class BrokerConfig
{
  use Nette\SmartObject;

  protected $authUsername;

  protected $authPassword;

  public function __construct($config)
  {
    $this->authUsername = Arrays::get($config, "auth", "username");
    $this->authPassword = Arrays::get($config, "auth", "password");
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