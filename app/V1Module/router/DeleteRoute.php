<?php

namespace App\V1Module\Router;

/**
 * HTTP Delete request route.
 */
class DeleteRoute extends MethodRoute
{
    /**
     * @param string $mask Mask for the Nette\Application\Routers\Route
     * @param string|array $metadata Metadata for the Nette\Application\Routers\Route
     */
    public function __construct(string $mask, $metadata = [])
    {
        parent::__construct("DELETE", $mask, $metadata);
    }
}
