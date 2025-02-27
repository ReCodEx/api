<?php

namespace App\Helpers\MetaFormats\Attributes;

use App\Exceptions\InternalServerException;
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
     * @param Type $type The request parameter type (Post or Query).
     * @param mixed $validators A validator object or an array of validators applied to the request parameter.
     * @param string $description The description of the request parameter.
     * @param bool $required Whether the request parameter is required.
     * @param bool $nullable Whether the request parameter can be null.
     */
    public function __construct(
        Type $type,
        mixed $validators,
        string $description = "",
        bool $required = true,
        bool $nullable = false,
    ) {
        $this->type = $type;
        $this->description = $description;
        $this->required = $required;
        $this->nullable = $nullable;

        // assign validators
        if ($validators == null) {
            throw new InternalServerException("Parameter Attribute validators are mandatory.");
        }
        if (!is_array($validators)) {
            $this->validators = [ $validators ];
        } else {
            if (count($validators) == 0) {
                throw new InternalServerException("Parameter Attribute validators are mandatory.");
            }
            $this->validators = $validators;
        }
    }
}
