<?php

namespace App\Helpers\MetaFormats\Attributes;

use App\Helpers\MetaFormats\RequestParamType;
use Attribute;

/**
 * Attribute used to annotate format definition class fields.
 */
#[Attribute]
class FormatParameterAttribute
{
    public function __construct(RequestParamType $type, string $description = "", bool $required = true)
    {
    }
}
