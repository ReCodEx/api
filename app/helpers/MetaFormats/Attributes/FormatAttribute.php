<?php

namespace App\Helpers\MetaFormats\Attributes;

use Attribute;

/**
 * Attribute for format definitions and usings.
 */
#[Attribute]
class FormatAttribute
{
    public function __construct(string $format)
    {
    }
}
