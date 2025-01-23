<?php

namespace App\Helpers\MetaFormats\Attributes;

use Attribute;

/**
 * Attribute for format definitions and usings.
 */
#[Attribute]
class FormatAttribute
{
    public string $class;

    public function __construct(string $class)
    {
        $this->class = $class;
    }
}
