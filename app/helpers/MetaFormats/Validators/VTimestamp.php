<?php

namespace App\Helpers\MetaFormats\Validators;

use App\Helpers\MetaFormats\PhpTypes;
use App\Helpers\MetaFormats\PrimitiveFormatValidators;

/**
 * Expects unix timestamps.
 */
class VTimestamp extends VInt
{
    public function getExampleValue(): string
    {
        return "1740135333";
    }
}
