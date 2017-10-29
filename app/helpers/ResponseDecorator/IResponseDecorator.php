<?php

namespace App\Helpers;

/**
 * Interface for decorators, which augment the presenter output just before it is passed on to JSON serialization.
 */
interface IResponseDecorator
{
  /**
   * The decorating function gets the payload being submitted to JSON serialization
   * and returns augmented payload.
   * @param $payload Original payload
   */
  public function decorate($payload);
}
