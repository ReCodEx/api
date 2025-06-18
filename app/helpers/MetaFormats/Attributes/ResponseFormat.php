<?php

namespace App\Helpers\MetaFormats\Attributes;

use Attribute;

/**
 * Attribute defining response format on endpoints.
 */
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
class ResponseFormat
{
    public readonly string $format;
    public readonly int $statusCode;

    public function __construct(string $format, int $statusCode = 200)
    {
        $this->format = $format;
        $this->statusCode = $statusCode;
    }
}
