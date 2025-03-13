<?php

namespace App\Helpers\MetaFormats\Attributes;

use App\Helpers\MetaFormats\Type;
use Attribute;

/**
 * Attribute used to annotate format definition properties representing query parameters.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class FQuery extends FormatParameterAttribute
{
    /**
     * @param mixed $validators A validator object or an array of validators applied to the request parameter.
     * @param string $description The description of the request parameter.
     * @param bool $required Whether the request parameter is required.
     * @param bool $nullable Whether the request parameter can be null.
     */
    public function __construct(
        mixed $validators,
        string $description = "",
        bool $required = true,
        bool $nullable = false,
    ) {
        parent::__construct(Type::Query, $validators, $description, $required, $nullable);
        // do not use json validation for query params
        $this->disableJsonValidation();
    }
}
