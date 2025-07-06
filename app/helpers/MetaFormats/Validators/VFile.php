<?php

namespace App\Helpers\MetaFormats\Validators;

use App\Helpers\MetaFormats\FileRequestType;

/**
 * Validates files. Currently, all files are valid.
 */
class VFile extends BaseValidator
{
    public const SWAGGER_TYPE = "string";
    public readonly FileRequestType $fileRequestType;

    public function __construct(FileRequestType $fileRequestType)
    {
        parent::__construct(strict: false);
        $this->fileRequestType = $fileRequestType;
    }

    public function validate(mixed $value): bool
    {
        return true;
    }
}
