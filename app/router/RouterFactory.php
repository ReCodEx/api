<?php

namespace App;

use Nette;
use Nette\Application\Routers\RouteList;
use Nette\Application\Routers\Route;


class RouterFactory
{
  use Nette\StaticClass;

  /**
    * @return Nette\Application\IRouter
    */
  public static function createRouter()
  {
    $router = new RouteList;
    $router[] = V1Module\RouterFactory::createRouter();
    return $router;
  }
}
