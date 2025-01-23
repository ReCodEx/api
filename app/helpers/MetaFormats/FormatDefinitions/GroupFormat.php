<?php

namespace App\Helpers\MetaFormats\FormatDefinitions;

use App\Helpers\MetaFormats\Attributes\FormatAttribute;
use App\Helpers\MetaFormats\MetaFormat;

#[FormatAttribute(GroupFormat::class)]
class GroupFormat extends MetaFormat
{
    // #[FormatAttribute("uuid")]
    // public string $id;
    // #[FormatAttribute("uuid")]
    // public string $externalId;
    // public bool $organizational;
    // public bool $exam;
    // public bool $archived;
    // public bool $public;
    // public bool $directlyArchived;
    // #[FormatAttribute("localizedText[]")]
    // public array $localizedTexts;
    // #[FormatAttribute("uuid[]")]
    // public array $primaryAdminsIds;
    // #[FormatAttribute("uuid?")]
    // public string $parentGroupId;
    // #[FormatAttribute("uuid[]")]
    // public array $parentGroupsIds;
    // #[FormatAttribute("uuid[]")]
    // public array $childGroups;
    // #[FormatAttribute("groupPrivateData")]
    // public $privateData;
    // #[FormatAttribute("acl[]")]
    // public array $permissionHints;
}
