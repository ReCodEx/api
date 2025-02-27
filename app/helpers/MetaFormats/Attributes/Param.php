<?php

namespace App\Helpers\MetaFormats\Attributes;

use App\Helpers\MetaFormats\Type;
use Attribute;

/**
 * Attribute used to annotate individual post or query parameters of endpoints.
 */
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
class Param extends FormatParameterAttribute
{
    public string $paramName;

    /**
     * @param Type $type The request parameter type (Post or Query).
     * @param string $name The name of the request parameter.
     * @param mixed $validators A validator object or an array of validators applied to the request parameter.
     * @param string $description The description of the request parameter.
     * @param bool $required Whether the request parameter is required.
     * @param bool $nullable Whether the request parameter can be null.
     */
    public function __construct(
        Type $type,
        string $name,
        mixed $validators,
        string $description = "",
        bool $required = true,
        bool $nullable = false,
    ) {
        parent::__construct($type, $validators, $description, $required, $nullable);
        $this->paramName = $name;
    }
}
