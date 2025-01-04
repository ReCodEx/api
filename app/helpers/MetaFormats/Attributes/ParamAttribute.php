<?php

namespace App\Helpers\MetaFormats\Attributes;

use App\Helpers\MetaFormats\RequestParamType;
use Attribute;

/**
 * Attribute used to annotate individual post or query parameters of endpoints.
 */
#[Attribute]
class ParamAttribute
{
    /**
     * @param \App\Helpers\MetaFormats\RequestParamType $type The request parameter type (Post or Query).
     * @param string $name The name of the request parameter.
     * @param string $description The description of the request parameter.
     * @param bool $required Whether the request parameter is required.
     * @param array $validators An array of validators applied to the request parameter.
     */
    public function __construct(
        RequestParamType $type,
        string $name,
        string $description = "",
        bool $required = true,
        array $validators = [],
    ) {
    }
}
