<?php

namespace App\Helpers\MetaFormats\FormatDefinitions;

use App\Helpers\MetaFormats\Attributes\Format;
use App\Helpers\MetaFormats\MetaFormat;
use App\Helpers\MetaFormats\Attributes\FPost;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VMixed;

#[Format(SuccessResponseFormat::class)]
class SuccessResponseFormat extends MetaFormat
{
    #[FPost(new VBool())]
    public bool $success;

    #[FPost(new VInt())]
    public int $code;

    #[FPost(new VMixed())]
    public mixed $payload;
}
