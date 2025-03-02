<?php

namespace App\Helpers\MetaFormats\Validators;

use App\Exceptions\InternalServerException;
use App\Helpers\MetaFormats\FormatCache;
use App\Helpers\MetaFormats\MetaFormat;

/**
 * Validates formats. Accepts any format derived of the base MetaFormat.
 * Format fields are validated by validators added to the fields.
 */
class VFormat
{
    public const SWAGGER_TYPE = "object";
    public string $format;

    public function __construct(string $format)
    {
        $this->format = $format;

        // throw immediatelly if the format does not exist
        if (!FormatCache::formatExists($format)) {
            throw new InternalServerException("Format $format does not exist.");
        }
    }

    public function getExampleValue()
    {
        ///TODO
        return "0";
    }

    public function validate(mixed $value)
    {
        // fine-grained checking is done in the properties
        return $value instanceof MetaFormat;
    }
}
