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
    public readonly string $description;
    public readonly int $statusCode;
    public readonly bool $useSuccessWrapper;

    /**
     * @param string $format The Format class that will be used for the response schema.
     * @param string $description The description of the response.
     * @param int $statusCode The status code of the response.
     * @param bool $useSuccessWrapper Whether the reponse will be wrapped with
     *  the "BasePresenter::sendSuccessResponse" method.
     */
    public function __construct(
        string $format,
        string $description = "Response data",
        int $statusCode = 200,
        bool $useSuccessWrapper = true
    ) {
        $this->format = $format;
        $this->description = $description;
        $this->statusCode = $statusCode;
        $this->useSuccessWrapper = $useSuccessWrapper;
    }
}
