<?php

namespace App\Helpers\MetaFormats\Attributes;

use Attribute;

/**
 * Attribute defining response format on endpoints.
 */
#[Attribute]
class ResponseFormat
{
    public string $class;

    public function __construct(string $class)
    {
        $this->class = $class;
    }
}
