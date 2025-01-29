<?php

namespace App\Helpers\MetaFormats\Attributes;

use App\Helpers\MetaFormats\Type;
use Attribute;

/**
 * Attribute used to annotate format definition class fields.
 */
#[Attribute]
class FormatParameterAttribute
{
    public Type $type;
    public array $validators;
    public string $description;
    public bool $required;
    // there is not an easy way to check whether a property has the nullability flag set
    public bool $nullable;

    /**
     * @param \App\Helpers\MetaFormats\Type $type The request parameter type (Post or Query).
     * @param array $validators An array of validators applied to the request parameter.
     * @param string $description The description of the request parameter.
     * @param bool $required Whether the request parameter is required.
     * @param bool $nullable Whether the request parameter can be null.
     */
    public function __construct(
        Type $type,
        array $validators = [],
        string $description = "",
        bool $required = true,
        bool $nullable = false,
    ) {
        $this->type = $type;
        $this->validators = $validators;
        $this->description = $description;
        $this->required = $required;
        $this->nullable = $nullable;
    }
}
