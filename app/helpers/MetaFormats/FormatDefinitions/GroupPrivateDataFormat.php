<?php

namespace App\Helpers\MetaFormats\FormatDefinitions;

use App\Helpers\MetaFormats\Attributes\Format;
use App\Helpers\MetaFormats\MetaFormat;
use App\Helpers\MetaFormats\Attributes\FPost;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VDouble;
use App\Helpers\MetaFormats\Validators\VEmail;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Helpers\MetaFormats\Validators\VUuid;
use ArrayAccess;

#[Format(GroupPrivateDataFormat::class)]
class GroupPrivateDataFormat extends MetaFormat
{
    #[FPost(new VArray(new VUuid()), "IDs of all users that have admin privileges to this group (including inherited)")]
    public array $admins;

    #[FPost(new VArray(new VUuid()))]
    public array $supervisors;

    #[FPost(new VArray(new VUuid()))]
    public array $observers;

    #[FPost(new VArray(new VUuid()))]
    public array $students;

    #[FPost(new VUuid(), required: false)]
    public string $instanceId;

    #[FPost(new VBool())]
    public bool $hasValidLicence;

    #[FPost(new VArray(new VUuid()))]
    public array $assignments;

    #[FPost(new VArray(new VUuid()))]
    public array $shadowAssignments;

    #[FPost(new VBool())]
    public bool $publicStats;

    #[FPost(new VBool())]
    public bool $detaining;

    #[FPost(new VDouble(), required: false)]
    public ?float $threshold;

    #[FPost(new VInt(), required: false)]
    public ?int $pointsLimit;

    #[FPost(new VArray())]
    public array $bindings;

    #[FPost(new VTimestamp(), required: false)]
    public ?int $examBegin;

    #[FPost(new VTimestamp(), required: false)]
    public ?int $examEnd;

    #[FPost(new VBool(), required: false)]
    public ?bool $examLockStrict;

    #[FPost(new VArray())]
    public array $exams;
}
