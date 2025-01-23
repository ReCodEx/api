<?php

namespace App\Helpers\MetaFormats\Attributes;

use App\Helpers\MetaFormats\RequestParamType;
use Attribute;

/**
 * Attribute used to annotate individual post or query parameters of endpoints.
 */
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
class RequestParamAttribute
{
    public RequestParamType $type;
    public string $paramName;
    public string $description;
    public bool $required;
    public array $validators;
    public bool $nullable;

    /**
     * @param \App\Helpers\MetaFormats\RequestParamType $type The request parameter type (Post or Query).
     * @param string $name The name of the request parameter.
     * @param string $description The description of the request parameter.
     * @param bool $required Whether the request parameter is required.
     * @param array $validators An array of validators applied to the request parameter.
     * @param bool $nullable Whether the request parameter can be null.
     */
    public function __construct(
        RequestParamType $type,
        string $name,
        string $description = "",
        bool $required = true,
        array $validators = [],
        bool $nullable = false,
    ) {
        $this->type = $type;
        $this->paramName = $name;
        $this->description = $description;
        $this->required = $required;
        $this->validators = $validators;
        $this->nullable = $nullable;
    }
}
