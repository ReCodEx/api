<?php
namespace App\Helpers;
use Nette;
use Nette\Utils\Arrays;

class MonitorConfig
{
  use Nette\SmartObject;

  protected $address;

  public function __construct($config)
  {
    $this->address = Arrays::get($config, "address", "");
  }

  public function getAddress()
  {
    return $this->address;
  }
}