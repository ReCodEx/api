<?php

namespace App\Helpers\MetaFormats\FormatDefinitions;

use App\Helpers\MetaFormats\Attributes\Format;
use App\Helpers\MetaFormats\MetaFormat;
use App\Helpers\MetaFormats\Attributes\FQuery;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VString;

/**
 * Format definition used by the GroupsPresenter.
 */
#[Format(UserFilterFormat::class)]
class UserFilterFormat extends MetaFormat
{
    #[FQuery(new VString(), "A search pattern", required: false)]
    public ?string $search;

    #[FQuery(new VString(), "The instance ID of the user", required: false)]
    public ?string $instanceId;

    #[FQuery(new VArray(new VString()), "The roles of the user", required: false)]
    public ?array $roles;
}
