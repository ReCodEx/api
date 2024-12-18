<?php

namespace App\Helpers\MetaFormats;

use Attribute;

/**
 * Attribute for request parameter details.
 */
#[Attribute]
class RequestAttribute
{
    public function __construct(RequestParamType $type, string $description = "", bool $required = true)
    {
    }
}
