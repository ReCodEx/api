<?php

namespace App;

use Nette;
use Nette\Application\Routers\RouteList;

/**
 * Main router factory which is used to create all possible routes.
 */
class RouterFactory
{
    use Nette\StaticClass;

    /**
     * Create list of routes from all modules.
     * @return Nette\Application\IRouter
     */
    public static function createRouter()
    {
        $router = new RouteList();
        $router[] = V1Module\RouterFactory::createRouter();
        return $router;
    }
}
