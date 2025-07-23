<?php

namespace App\Helpers\MetaFormats\FormatDefinitions;

use App\Helpers\MetaFormats\Attributes\Format;
use App\Helpers\MetaFormats\MetaFormat;
use App\Helpers\MetaFormats\Attributes\FPost;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VMixed;

/**
 * Wrapper Format definition of the common output schema.
 * The ResponseFormat attribute has a flag that can automatically wrap any Format with this one.
 */
#[Format(SuccessResponseFormat::class)]
class SuccessResponseFormat extends MetaFormat
{
    #[FPost(new VBool(), "Whether the request was processed successfully.")]
    public bool $success;

    #[FPost(new VInt(), "HTTP response code.")]
    public int $code;

    #[FPost(new VMixed(), "The payload of the response.")]
    public mixed $payload;
}
