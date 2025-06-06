<?php

namespace App\V1Module\Router;

use Nette\Http\IRequest;
use Nette\Application\Routers\Route;
use Nette\Http\UrlScript;
use Nette\Routing\Router;

/**
 * Base class of all module routes which construct URLs. And also does all
 * checking of HTTP request method against the one given during construction.
 */
class MethodRoute implements Router
{
    /** @var string */
    private $method;

    /** @var Route */
    private $route;

    /**
     * @param string $method The HTTP method which is accepted by this route
     * @param string $mask Mask for the Nette\Application\Routers\Route
     * @param string|array $metadata Metadata for the Nette\Application\Routers\Route
     */
    public function __construct(string $method, string $mask, $metadata = [])
    {
        $this->method = $method;
        $this->route = new Route($mask, $metadata);
    }

    /**
     * Maps HTTP request to a Request object.
     * @return array|null
     */
    public function match(IRequest $httpRequest): ?array
    {
        if (!$httpRequest->isMethod($this->method)) {
            return null;
        }

        return $this->route->match($httpRequest);
    }

    /**
     * Constructs absolute URL from Request object.
     * @return string|null
     */
    public function constructUrl(array $params, UrlScript $refUrl): ?string
    {
        return $this->route->constructUrl($params, $refUrl);
    }
}
