<?php

namespace App\Helpers\MetaFormats\FormatDefinitions;

use App\Helpers\MetaFormats\Attributes\Format;
use App\Helpers\MetaFormats\MetaFormat;
use App\Helpers\MetaFormats\Attributes\FPost;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VEmail;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VUuid;
use ArrayAccess;

/**
 * Format definition used by the RegistrationPresenter::actionCreateInvitation endpoint.
 */
#[Format(GroupFormat::class)]
class GroupFormat extends MetaFormat// implements ArrayAccess
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

    #[FPost(new VMixed(), "")]
    public mixed $privateData;

    #[FPost(new VArray(), "")]
    public ?array $permissionHints;


    // public function offsetExists(mixed $offset): bool
    // {
    //     return isset($this->$offset);
    // }

    // /**
    //  * Offset to retrieve
    //  * @param mixed $offset The offset to retrieve.
    //  * @return mixed Can return all value types.
    //  */
    // public function offsetGet(mixed $offset): mixed
    // {
    //     return $this->$offset ?? null;
    // }

    // /**
    //  * Offset to set
    //  * @param mixed $offset The offset to assign the value to.
    //  * @param mixed $value The value to set.
    //  */
    // public function offsetSet(mixed $offset, mixed $value): void
    // {
    //     $this->$offset = $value;
    // }

    // /**
    //  * Offset to unset
    //  * @param mixed $offset The offset to unset.
    //  */
    // public function offsetUnset(mixed $offset): void
    // {
    //     $this->$offset = null;
    // }
}
