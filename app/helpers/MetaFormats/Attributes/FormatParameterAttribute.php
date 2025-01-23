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
    public RequestParamType $type;
    public string $description;
    public bool $required;
    public array $validators;

    /**
     * @param \App\Helpers\MetaFormats\RequestParamType $type The request parameter type (Post or Query).
     * @param string $description The description of the request parameter.
     * @param bool $required Whether the request parameter is required.
     * @param array $validators An array of validators applied to the request parameter.
     */
    public function __construct(
        RequestParamType $type,
        string $description = "",
        bool $required = true,
        array $validators = [],
    ) {
        $this->type = $type;
        $this->description = $description;
        $this->required = $required;
        $this->validators = $validators;
    }
}
