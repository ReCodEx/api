<?php

namespace App\Helpers\MetaFormats;

/**
 * Parses format string enriched by nullability and array modifiers.
 * In case the format contains array, this data class can be recursive.
 * Example: string?[]? can either be null or of string?[] type, an array of nullable strings
 * Example2: string[]?[] is an array of null or string arrays
 */
class FormatParser
{
    public bool $nullable = false;
    public bool $isArray = false;
    // contains the format stripped of the nullability ?, null if it is an array
    public ?string $format = null;
    // contains the format definition of nested elements, null if it is not an array
    public ?FormatParser $nested = null;

    public function __construct(string $format)
    {
        // check nullability
        if (str_ends_with($format, "?")) {
            $this->nullable = true;
            $format = substr($format, 0, -1);
        }

        // check array
        if (str_ends_with($format, "[]")) {
            $this->isArray = true;
            $format = substr($format, 0, -2);
            $this->nested = new FormatParser($format);
        } else {
            $this->format = $format;
        }
    }
}
