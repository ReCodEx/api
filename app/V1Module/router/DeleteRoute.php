<?php

namespace App\V1Module\Router;

class DeleteRoute extends MethodRoute {

  /**
   * @param string        $mask       Mask for the Nette\Application\Routers\Route
   * @param string|array  $metadata   Metadata for the Nette\Application\Routers\Route
   * @param int           $flags      Flags for the Nette\Application\Routers\Route
   */
  public function __construct(string $mask, $metadata = [], int $flags = 0) {
    parent::__construct("DELETE", $mask, $metadata, $flags);
  }

}
