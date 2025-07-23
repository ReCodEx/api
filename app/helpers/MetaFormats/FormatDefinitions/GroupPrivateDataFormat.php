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

    #[FPost(new VUuid(), "ID of an instance in which the group belongs", required: false)]
    public string $instanceId;

    #[FPost(new VBool(), "Whether the instance where the group belongs has a valid license")]
    public bool $hasValidLicence;

    #[FPost(new VArray(new VUuid()), "IDs of all group assignments")]
    public array $assignments;

    #[FPost(new VArray(new VUuid()), "IDs of all group shadow assignments")]
    public array $shadowAssignments;

    #[FPost(new VBool(), "Whether the student's results are visible to other students")]
    public bool $publicStats;

    #[FPost(new VBool(), "Whether the group detains the students (so they can be released only by the teacher)")]
    public bool $detaining;

    #[FPost(
        new VDouble(),
        "A relative number of points a student must receive from assignments to fulfill the requirements of the group",
        required: false,
    )]
    public ?float $threshold;

    #[FPost(
        new VInt(),
        "A minimal number of points that a student must receive to fulfill the group's requirements",
        required: false,
    )]
    public ?int $pointsLimit;

    #[FPost(new VArray(), "Entities bound to the group")]
    public array $bindings;

    #[FPost(new VTimestamp(), "The time when the exam starts if there is an exam scheduled", required: false)]
    public ?int $examBegin;

    #[FPost(new VTimestamp(), "The time when the exam ends if there is an exam scheduled", required: false)]
    public ?int $examEnd;

    #[FPost(new VBool(), "Whether the scheduled exam requires a strict access lock", required: false)]
    public ?bool $examLockStrict;

    #[FPost(new VArray(), "All past exams (with at least one student locked)")]
    public array $exams;
}
