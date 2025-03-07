<?php

namespace App\Helpers\MetaFormats\Validators;

/**
 * Validates unix timestamps.
 */
class VTimestamp extends VInt
{
    public function getExampleValue(): string
    {
        return "1740135333";
    }
}
