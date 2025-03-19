<?php

namespace App\Helpers\MetaFormats\Validators;

use App\Exceptions\InternalServerException;
use App\Helpers\MetaFormats\FormatCache;
use App\Helpers\MetaFormats\MetaFormat;

/**
 * Validates formats. Accepts any format derived of the base MetaFormat.
 * Format fields are validated by validators added to the fields.
 */
class VObject extends BaseValidator
{
    public const SWAGGER_TYPE = "object";
    public string $format;

    public function __construct(string $format, bool $strict = true)
    {
        parent::__construct($strict);
        $this->format = $format;

        // throw immediately if the format does not exist
        if (!FormatCache::formatExists($format)) {
            throw new InternalServerException("Format $format does not exist.");
        }
    }

    public function validate(mixed $value): bool
    {
        // fine-grained checking is done in the properties
        return $value instanceof MetaFormat;
    }
}
