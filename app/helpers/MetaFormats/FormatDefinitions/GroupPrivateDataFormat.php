<?php

namespace App\Helpers\MetaFormats\FormatDefinitions;

use App\Helpers\MetaFormats\Attributes\Format;
use App\Helpers\MetaFormats\MetaFormat;
use App\Helpers\MetaFormats\Attributes\FPost;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VDouble;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Helpers\MetaFormats\Validators\VUuid;

/**
 * Nested Format definition used by the GroupFormat.
 */
#[Format(GroupPrivateDataFormat::class)]
class GroupPrivateDataFormat extends MetaFormat
{
    #[FPost(new VArray(new VUuid()), "IDs of all users that have admin privileges to this group (including inherited)")]
    public array $admins;

    #[FPost(new VArray(new VUuid()), "IDs of all group supervisors")]
    public array $supervisors;

    #[FPost(new VArray(new VUuid()), "IDs of all group observers")]
    public array $observers;

    #[FPost(new VArray(new VUuid()), "IDs of the students of this group")]
    public array $students;

    #[FPost(new VUuid(), "The instance ID of the group", required: false)]
    public string $instanceId;

    #[FPost(new VBool(), "Whether the group has a valid license")]
    public bool $hasValidLicence;

    #[FPost(new VArray(new VUuid()), "IDs of all group assignments")]
    public array $assignments;

    #[FPost(new VArray(new VUuid()), "IDs of all group shadow assignments")]
    public array $shadowAssignments;

    #[FPost(new VBool(), "Whether the group statistics are public")]
    public bool $publicStats;

    #[FPost(new VBool(), "Whether the group is detaining")]
    public bool $detaining;

    #[FPost(new VDouble(), "The group assignment point threshold", required: false)]
    public ?float $threshold;

    #[FPost(new VInt(), "The group points limit", required: false)]
    public ?int $pointsLimit;

    #[FPost(new VArray(), "Entities bound to the group")]
    public array $bindings;

    #[FPost(new VTimestamp(), "The time when the exam starts if there is an exam", required: false)]
    public ?int $examBegin;

    #[FPost(new VTimestamp(), "The time when the exam ends if there is an exam", required: false)]
    public ?int $examEnd;

    #[FPost(new VBool(), "Whether there is a strict exam lock", required: false)]
    public ?bool $examLockStrict;

    #[FPost(new VArray(), "All group exams")]
    public array $exams;
}
