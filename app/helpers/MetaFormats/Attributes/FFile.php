<?php

namespace App\Helpers\MetaFormats\Attributes;

use App\Helpers\MetaFormats\FileRequestType;
use App\Helpers\MetaFormats\Type;
use App\Helpers\MetaFormats\Validators\VFile;
use Attribute;

/**
 * Attribute used to annotate format definition properties representing a file parameter.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class FFile extends FormatParameterAttribute
{
    /**
     * @param FileRequestType $fileRequestType How will the file be transmitted in the request.
     * @param string $description The description of the request parameter.
     * @param bool $required Whether the request parameter is required.
     */
    public function __construct(
        FileRequestType $fileRequestType,
        string $description = "",
        bool $required = true,
    ) {
        parent::__construct(Type::File, new VFile($fileRequestType), $description, $required, false);
    }
}
