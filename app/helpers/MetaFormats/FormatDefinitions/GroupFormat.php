<?php

namespace App\Helpers\MetaFormats\FormatDefinitions;

use App\Helpers\MetaFormats\Attributes\Format;
use App\Helpers\MetaFormats\MetaFormat;
use App\Helpers\MetaFormats\Attributes\FPost;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VObject;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VUuid;

/**
 * Format definition used by the GroupsPresenter.
 */
#[Format(GroupFormat::class)]
class GroupFormat extends MetaFormat
{
    #[FPost(new VUuid(), "An identifier of the group")]
    public string $id;

    #[FPost(
        new VString(),
        "An informative, human readable identifier of the group",
        required: false,
        nullable: true,
    )]
    public ?string $externalId;

    #[FPost(
        new VBool(),
        "Whether the group is organizational (no assignments nor students).",
        required: false,
    )]
    public ?bool $organizational;

    #[FPost(new VBool(), "Whether the group is an exam group.", required: false)]
    public ?bool $exam;

    #[FPost(new VBool(), "Whether the group is archived", required: false)]
    public ?bool $archived;

    #[FPost(new VBool(), "Should the group be visible to all student?")]
    public ?bool $public;

    #[FPost(new VBool(), "Whether the group was explicitly marked as archived")]
    public ?bool $directlyArchived;

    #[FPost(new VArray(), "Localized names and descriptions", required: false)]
    public ?array $localizedTexts;

    #[FPost(new VArray(new VUuid()), "IDs of users which are explicitly listed as direct admins of this group")]
    public ?array $primaryAdminsIds;

    #[FPost(
        new VUuid(),
        "Identifier of the parent group (absent for a top-level group)",
        required: false,
    )]
    public ?string $parentGroupId;

    #[FPost(new VArray(new VUuid()), "Identifications of groups in descending order.")]
    public ?array $parentGroupsIds;

    #[FPost(new VArray(new VUuid()), "Identifications of child groups.")]
    public ?array $childGroups;

    #[FPost(new VObject(GroupPrivateDataFormat::class), required: false)]
    public ?GroupPrivateDataFormat $privateData;

    #[FPost(new VArray())]
    public ?array $permissionHints;
}
